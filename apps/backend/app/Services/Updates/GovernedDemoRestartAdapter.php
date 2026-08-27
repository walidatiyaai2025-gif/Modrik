<?php

namespace App\Services\Updates;

use RuntimeException;
use Throwable;

final class GovernedDemoRestartAdapter implements WebRestartAdapter
{
    public function __construct(private DemoActivationHealthVerifier $health) {}

    public function restart(string $releasePath): RestartResult
    {
        try {
            $candidate = $this->candidate($releasePath);
        } catch (Throwable $e) {
            return RestartResult::failed('The staged release does not satisfy the governed Demo runtime contract.', [
                'reason' => $this->safeReason($e->getMessage(), 'candidate_invalid'),
            ]);
        }

        if (! (bool) config('update_center.demo.hosting_bridge_enabled', false)) {
            return RestartResult::requiresHostAction(
                'The Demo hosting bridge is disabled. Activate the candidate through the governed cPanel deployment path before restart verification.',
                ['reason' => 'hosting_bridge_disabled', 'release_sha' => $candidate['release_sha']],
            );
        }

        $configuration = $this->configuration();
        if ($configuration === null) {
            return RestartResult::requiresHostAction(
                'The locked Demo hosting coordinates are incomplete or invalid.',
                ['reason' => 'hosting_configuration_invalid', 'release_sha' => $candidate['release_sha']],
            );
        }

        $liveSha = $this->readReleaseSha($configuration['web_root'].DIRECTORY_SEPARATOR.'RELEASE_SHA.txt');
        if ($liveSha !== $candidate['release_sha']) {
            return RestartResult::requiresHostAction(
                'The fixed Demo Web root does not contain the candidate release. Payload activation must be completed by the governed deployment transaction before a restart can be requested.',
                [
                    'reason' => 'live_payload_activation_required',
                    'release_sha' => $candidate['release_sha'],
                    'live_release_sha' => $liveSha,
                ],
            );
        }

        $selector = $this->resolveSelector($configuration['selector_bin']);
        if ($selector === null) {
            return RestartResult::requiresHostAction(
                'CloudLinux Node.js Selector is unavailable to the application runtime.',
                ['reason' => 'selector_unavailable', 'release_sha' => $candidate['release_sha']],
            );
        }

        $state = $this->selectorState($selector, $configuration, false);
        if ($state['kind'] === 'unknown') {
            return RestartResult::unknown(
                'CloudLinux Node.js Selector state could not be determined safely.',
                ['reason' => $state['reason'], 'release_sha' => $candidate['release_sha']],
            );
        }
        if ($state['kind'] !== 'ok') {
            return RestartResult::requiresHostAction(
                'The configured Demo Node application does not match the locked hosting desired state.',
                ['reason' => $state['reason'], 'release_sha' => $candidate['release_sha']],
            );
        }

        $restart = $this->selectorCommand($selector, 'restart', $configuration);
        if ($restart['kind'] === 'unknown') {
            return RestartResult::unknown(
                'The bounded CloudLinux restart command timed out, so its outcome is unknown.',
                ['reason' => 'restart_timeout', 'release_sha' => $candidate['release_sha'], 'restart_attempts' => 1],
            );
        }
        if ($restart['kind'] !== 'ok') {
            return RestartResult::failed(
                'CloudLinux rejected the bounded Demo restart request.',
                ['reason' => 'restart_failed', 'release_sha' => $candidate['release_sha'], 'restart_attempts' => 1],
            );
        }

        $verificationAttempts = 0;
        $lastReason = 'activation_not_converged';
        for ($verificationAttempts = 1; $verificationAttempts <= 3; $verificationAttempts++) {
            if ($verificationAttempts > 1) {
                sleep(2);
            }

            $postState = $this->selectorState($selector, $configuration, true);
            if ($postState['kind'] === 'unknown') {
                return RestartResult::unknown(
                    'The restart was accepted but the post-restart hosting state could not be determined safely.',
                    [
                        'reason' => $postState['reason'],
                        'release_sha' => $candidate['release_sha'],
                        'restart_attempts' => 1,
                        'verification_attempts' => $verificationAttempts,
                    ],
                );
            }
            if ($postState['kind'] !== 'ok') {
                $lastReason = $postState['reason'];

                continue;
            }

            $health = $this->health->verify($candidate['release_sha']);
            if ($health['ok']) {
                return RestartResult::success(
                    'The Demo Node runtime restarted once and exact-release activation health checks passed.',
                    [
                        'release_sha' => $candidate['release_sha'],
                        'restart_attempts' => 1,
                        'verification_attempts' => $verificationAttempts,
                        'health_checks' => $health['checks'],
                    ],
                );
            }

            $lastReason = $health['reason'] ?? 'activation_health_failed';
        }

        return RestartResult::failed(
            'The bounded Demo restart completed, but exact-release activation did not converge.',
            [
                'reason' => $lastReason,
                'release_sha' => $candidate['release_sha'],
                'restart_attempts' => 1,
                'verification_attempts' => max(1, $verificationAttempts - 1),
            ],
        );
    }

    /** @return array{release_sha:string} */
    private function candidate(string $releasePath): array
    {
        $root = realpath($releasePath);
        if ($root === false || ! is_dir($root)) {
            throw new RuntimeException('candidate_path_invalid');
        }

        $manifestPath = $root.DIRECTORY_SEPARATOR.'manifest.json';
        $manifest = json_decode((string) @file_get_contents($manifestPath), true);
        $releaseSha = is_array($manifest) && is_string($manifest['release_sha'] ?? null)
            ? strtolower(trim($manifest['release_sha']))
            : '';
        if (preg_match('/^[0-9a-f]{40}$/', $releaseSha) !== 1) {
            throw new RuntimeException('candidate_release_identity_invalid');
        }

        $webRoot = $root.DIRECTORY_SEPARATOR.'payload'.DIRECTORY_SEPARATOR.'web';
        $webSha = $this->readReleaseSha($webRoot.DIRECTORY_SEPARATOR.'RELEASE_SHA.txt');
        if ($webSha !== $releaseSha) {
            throw new RuntimeException('candidate_web_release_identity_mismatch');
        }
        if (trim((string) @file_get_contents($webRoot.DIRECTORY_SEPARATOR.'WEB_APPLICATION_ROOT.txt')) !== '.') {
            throw new RuntimeException('candidate_web_application_root_invalid');
        }
        if (! is_file($webRoot.DIRECTORY_SEPARATOR.'server.js')) {
            throw new RuntimeException('candidate_web_startup_missing');
        }

        return ['release_sha' => $releaseSha];
    }

    /**
     * @return array{web_root:string,node_app_root:string,domain:string,node_major:int,origin_ip:string,selector_bin:string,cagefs_bin:string,timeout:int}|null
     */
    private function configuration(): ?array
    {
        $webRoot = rtrim((string) config('update_center.demo.web_root', ''), DIRECTORY_SEPARATOR);
        $nodeAppRoot = trim((string) config('update_center.demo.node_app_root', ''), '/');
        $domain = strtolower(trim((string) config('update_center.demo.domain', '')));
        $nodeMajor = (int) config('update_center.demo.node_major', 0);
        $originIp = trim((string) config('update_center.demo.origin_ip', ''));
        $selectorBin = trim((string) config('update_center.demo.cloudlinux_selector_bin', ''));
        $cagefsBin = trim((string) config('update_center.demo.cagefs_enter_bin', ''));
        $timeout = max(5, min(60, (int) config('update_center.demo.selector_timeout_seconds', 20)));

        if ($webRoot === '' || ! str_starts_with($webRoot, DIRECTORY_SEPARATOR) || str_contains($webRoot, "\0")) {
            return null;
        }
        if ($nodeAppRoot === '' || str_contains($nodeAppRoot, '..') || preg_match('/[^A-Za-z0-9._\/-]/', $nodeAppRoot)) {
            return null;
        }
        if ($domain === '' || preg_match('/[^a-z0-9.-]/', $domain)) {
            return null;
        }
        if ($nodeMajor < 1 || $originIp === '' || filter_var($originIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return null;
        }

        return [
            'web_root' => $webRoot,
            'node_app_root' => $nodeAppRoot,
            'domain' => $domain,
            'node_major' => $nodeMajor,
            'origin_ip' => $originIp,
            'selector_bin' => $selectorBin,
            'cagefs_bin' => $cagefsBin,
            'timeout' => $timeout,
        ];
    }

    private function resolveSelector(string $configured): ?string
    {
        $candidates = $configured === ''
            ? ['/usr/sbin/cloudlinux-selector', '/usr/bin/cloudlinux-selector']
            : [$configured];

        foreach ($candidates as $candidate) {
            if (str_starts_with($candidate, '/') && is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array{web_root:string,node_app_root:string,domain:string,node_major:int,origin_ip:string,selector_bin:string,cagefs_bin:string,timeout:int}  $configuration
     * @return array{kind:string,reason:string}
     */
    private function selectorState(string $selector, array $configuration, bool $requireStarted): array
    {
        $command = $this->selectorCommand($selector, 'get', $configuration);
        if ($command['kind'] !== 'ok') {
            return ['kind' => $command['kind'], 'reason' => $command['kind'] === 'unknown' ? 'selector_state_timeout' : 'selector_state_failed'];
        }

        $decoded = json_decode($command['stdout'], true);
        if (! is_array($decoded) || ($decoded['result'] ?? null) !== 'success') {
            return ['kind' => 'mismatch', 'reason' => 'selector_state_invalid'];
        }

        $matches = [];
        $targetRoot = trim($configuration['node_app_root'], '/');
        $walk = static function (mixed $value, array $path = []) use (&$walk, &$matches, $targetRoot): void {
            if (! is_array($value)) {
                return;
            }
            $tail = $path === [] ? null : (string) end($path);
            $fieldRoot = $value['app_root'] ?? $value['app-root'] ?? null;
            $targetKey = $tail !== null && trim($tail, '/') === $targetRoot;
            $targetField = is_string($fieldRoot) && trim($fieldRoot, '/') === $targetRoot;
            if ($targetKey || $targetField) {
                $version = is_scalar($value['version'] ?? null) ? (string) $value['version'] : '';
                if ($version === '') {
                    foreach (array_reverse($path) as $segment) {
                        if (preg_match('/^\d+\.\d+\.\d+$/', (string) $segment)) {
                            $version = (string) $segment;
                            break;
                        }
                    }
                }
                $matches[] = [
                    'domain' => is_scalar($value['domain'] ?? null) ? strtolower(trim((string) $value['domain'])) : '',
                    'version' => $version,
                    'startup' => is_scalar($value['startup_file'] ?? $value['startup-file'] ?? null)
                        ? trim((string) ($value['startup_file'] ?? $value['startup-file']))
                        : '',
                    'mode' => is_scalar($value['app_mode'] ?? $value['mode'] ?? null)
                        ? strtolower(trim((string) ($value['app_mode'] ?? $value['mode'])))
                        : '',
                    'status' => is_scalar($value['app_status'] ?? $value['status'] ?? null)
                        ? strtolower(trim((string) ($value['app_status'] ?? $value['status'])))
                        : '',
                ];
            }
            foreach ($value as $key => $child) {
                if (is_array($child)) {
                    $walk($child, [...$path, (string) $key]);
                }
            }
        };
        $walk($decoded);

        $unique = [];
        foreach ($matches as $match) {
            $key = json_encode($match, JSON_UNESCAPED_SLASHES);
            if (is_string($key)) {
                $unique[$key] = $match;
            }
        }
        $matches = array_values($unique);
        if (count($matches) !== 1) {
            return ['kind' => 'mismatch', 'reason' => 'selector_target_ambiguous'];
        }

        $state = $matches[0];
        if ($state['domain'] !== $configuration['domain']) {
            return ['kind' => 'mismatch', 'reason' => 'selector_domain_mismatch'];
        }
        if (! preg_match('/^(\d+)\./', $state['version'], $version) || (int) $version[1] !== $configuration['node_major']) {
            return ['kind' => 'mismatch', 'reason' => 'selector_node_mismatch'];
        }
        if ($state['mode'] !== 'production') {
            return ['kind' => 'mismatch', 'reason' => 'selector_mode_mismatch'];
        }
        if ($state['startup'] !== 'server.js') {
            return ['kind' => 'mismatch', 'reason' => 'selector_startup_mismatch'];
        }
        if ($state['status'] === '') {
            return ['kind' => 'mismatch', 'reason' => 'selector_status_missing'];
        }
        if ($requireStarted && $state['status'] !== 'started') {
            return ['kind' => 'mismatch', 'reason' => 'selector_not_started'];
        }

        return ['kind' => 'ok', 'reason' => 'selector_state_ok'];
    }

    /**
     * @param  array{web_root:string,node_app_root:string,domain:string,node_major:int,origin_ip:string,selector_bin:string,cagefs_bin:string,timeout:int}  $configuration
     * @return array{kind:string,stdout:string}
     */
    private function selectorCommand(string $selector, string $action, array $configuration): array
    {
        $arguments = $action === 'get'
            ? [$selector, 'get', '--json', '--interpreter', 'nodejs']
            : [$selector, $action, '--json', '--interpreter', 'nodejs', '--app-root', $configuration['node_app_root']];

        $cagefs = $configuration['cagefs_bin'];
        if ($cagefs !== '' && str_starts_with($cagefs, '/') && is_file($cagefs) && is_executable($cagefs)) {
            array_unshift($arguments, $cagefs);
        } else {
            $arguments[] = '--user';
            $arguments[] = get_current_user();
        }

        return $this->run($arguments, $configuration['timeout']);
    }

    /**
     * @param  list<string>  $command
     * @return array{kind:string,stdout:string}
     */
    private function run(array $command, int $timeout): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        if (! is_resource($process)) {
            return ['kind' => 'failed', 'stdout' => ''];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $started = microtime(true);
        $status = proc_get_status($process);
        $timedOut = false;

        while (true) {
            $stdout .= (string) stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (! $status['running']) {
                break;
            }
            if ((microtime(true) - $started) >= $timeout) {
                $timedOut = true;
                proc_terminate($process, 15);
                usleep(100000);
                break;
            }
            usleep(100000);
        }

        $stdout .= (string) stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit === -1) {
            $exit = $status['exitcode'];
        }
        if ($timedOut) {
            return ['kind' => 'unknown', 'stdout' => ''];
        }

        return ['kind' => $exit === 0 ? 'ok' : 'failed', 'stdout' => $stdout];
    }

    private function readReleaseSha(string $path): ?string
    {
        $sha = is_readable($path) ? strtolower(trim((string) file_get_contents($path))) : '';

        return preg_match('/^[0-9a-f]{40}$/', $sha) === 1 ? $sha : null;
    }

    private function safeReason(string $reason, string $fallback): string
    {
        return preg_match('/^[a-z0-9_]{1,80}$/', $reason) === 1 ? $reason : $fallback;
    }
}

<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

final class TransactionalReleaseManager
{
    public function __construct(
        private UnifiedPackageValidator $validator,
        private WebRestartAdapter $restart,
        private BackendReleaseOperator $backend,
        private ActivationHealthChecker $health,
        private LivePayloadActivator $live,
    ) {}

    public function install(string $archive, string $root, ?string $currentVersion = null): UpdateExecutionResult
    {
        File::ensureDirectoryExists($root);
        $lock = $this->acquireLock($root);
        $maintenance = $root.DIRECTORY_SEPARATOR.'shared'.DIRECTORY_SEPARATOR.'maintenance.json';
        $staging = null;
        $candidate = null;
        $current = $root.DIRECTORY_SEPARATOR.'current';
        $previous = null;
        $liveActivation = null;

        try {
            if ($this->pendingActivation($root) !== null) {
                throw new RuntimeException('pending_activation_exists');
            }

            $validation = $this->validator->validate($archive, $currentVersion);
            if (! $validation->valid || ! is_array($validation->manifest)) {
                throw new RuntimeException('package_validation_failed');
            }
            $releaseId = $validation->manifest['version'].'-'.$validation->manifest['release_sha'];
            $releaseSha = strtolower((string) $validation->manifest['release_sha']);
            $staging = $root.DIRECTORY_SEPARATOR.'staging'.DIRECTORY_SEPARATOR.bin2hex(random_bytes(16));
            File::ensureDirectoryExists($staging, 0700);
            $zip = new ZipArchive;
            if ($zip->open($archive, ZipArchive::RDONLY) !== true || ! $zip->extractTo($staging)) {
                throw new RuntimeException('stage_failed');
            }
            $zip->close();
            $candidate = $root.DIRECTORY_SEPARATOR.'releases'.DIRECTORY_SEPARATOR.$releaseId.'-'.bin2hex(random_bytes(4));
            File::ensureDirectoryExists(dirname($candidate), 0700);
            if (! @rename($staging, $candidate)) {
                throw new RuntimeException('candidate_move_failed');
            }

            $this->backend->prepareSharedState($candidate, $root.DIRECTORY_SEPARATOR.'shared');
            File::ensureDirectoryExists(dirname($maintenance), 0700);
            File::put($maintenance, json_encode(['release_id' => $releaseId, 'started_at' => now()->toIso8601String()], JSON_THROW_ON_ERROR), true);

            if (! $this->backend->migrate($candidate)) {
                return new UpdateExecutionResult(UpdateExecutionResult::PARTIAL_REQUIRES_OPERATOR, $releaseId, null, [
                    'phase' => 'migration', 'code_activated' => false, 'database_rollback_attempted' => false,
                ]);
            }
            if (! $this->backend->rebuildCaches($candidate)) {
                return new UpdateExecutionResult(UpdateExecutionResult::PARTIAL_REQUIRES_OPERATOR, $releaseId, null, [
                    'phase' => 'cache_rebuild', 'code_activated' => false, 'database_rollback_attempted' => false,
                ]);
            }

            $previous = is_dir($current) ? $root.DIRECTORY_SEPARATOR.'releases'.DIRECTORY_SEPARATOR.'.previous-'.bin2hex(random_bytes(6)) : null;
            if ($previous !== null && ! @rename($current, $previous)) {
                throw new RuntimeException('current_backup_failed');
            }
            if (! @rename($candidate, $current)) {
                if ($previous !== null) {
                    @rename($previous, $current);
                }
                throw new RuntimeException('activation_failed');
            }

            try {
                $liveActivation = $this->live->activate($current, $root, $releaseId, $releaseSha);
            } catch (\Throwable $exception) {
                $rolledBack = $this->rollbackCode($root, $current, $previous, $releaseId);

                return new UpdateExecutionResult($rolledBack ? UpdateExecutionResult::ROLLED_BACK : UpdateExecutionResult::FAILED, $releaseId, $previous, [
                    'phase' => 'live_payload_activation',
                    'code_rolled_back' => $rolledBack,
                    'reason' => $this->safeCode($exception->getMessage(), 'live_payload_activation_failed'),
                ]);
            }

            $this->writePendingActivation($root, [
                'release_id' => $releaseId,
                'version' => (string) $validation->manifest['version'],
                'release_sha' => $releaseSha,
                'current' => $current,
                'previous' => $previous,
                'backup_path' => $liveActivation['backup_path'],
                'previous_release_sha' => $liveActivation['previous_release_sha'],
                'activated_at' => now()->toIso8601String(),
            ]);

            $restart = $this->restart->restart($current);
            if (! $restart->successful()) {
                if ($restart->status === 'requires_host_action') {
                    return new UpdateExecutionResult(UpdateExecutionResult::REQUIRES_HOST_ACTION, $releaseId, $previous, [
                        'phase' => 'host_confirmation',
                        'live_activated' => true,
                        'pending_confirmation' => true,
                        'restart_status' => $restart->status,
                        'restart_reason' => $this->safeRestartReason($restart),
                    ]);
                }

                $liveRolledBack = $this->live->rollback($liveActivation['backup_path'], $liveActivation['previous_release_sha']);
                $codeRolledBack = $this->rollbackCode($root, $current, $previous, $releaseId);
                $this->deletePendingActivation($root);

                return new UpdateExecutionResult($liveRolledBack && $codeRolledBack ? UpdateExecutionResult::ROLLED_BACK : UpdateExecutionResult::FAILED, $releaseId, $previous, [
                    'phase' => 'web_restart',
                    'code_rolled_back' => $codeRolledBack,
                    'live_rolled_back' => $liveRolledBack,
                    'restart_status' => $restart->status,
                    'restart_reason' => $this->safeRestartReason($restart),
                ]);
            }

            if (! $this->health->healthy($current, $releaseSha) || ! $this->live->runtimeHealthy($releaseSha)) {
                $liveRolledBack = $this->live->rollback($liveActivation['backup_path'], $liveActivation['previous_release_sha']);
                $codeRolledBack = $this->rollbackCode($root, $current, $previous, $releaseId);
                $this->deletePendingActivation($root);

                return new UpdateExecutionResult($liveRolledBack && $codeRolledBack ? UpdateExecutionResult::ROLLED_BACK : UpdateExecutionResult::FAILED, $releaseId, $previous, [
                    'phase' => 'activation_health',
                    'code_rolled_back' => $codeRolledBack,
                    'live_rolled_back' => $liveRolledBack,
                ]);
            }

            $this->completeActivation($root, (string) $validation->manifest['version'], $releaseSha);

            return new UpdateExecutionResult(UpdateExecutionResult::SUCCESS, $releaseId, $previous, [
                'phase' => 'complete', 'health_confirmed' => true, 'live_activated' => true,
            ]);
        } finally {
            if (is_file($maintenance)) {
                @unlink($maintenance);
            }
            if (is_string($staging) && is_dir($staging)) {
                File::deleteDirectory($staging);
            }
            $this->releaseLock($lock);
        }
    }

    public function verifyPending(string $root): UpdateExecutionResult
    {
        File::ensureDirectoryExists($root);
        $lock = $this->acquireLock($root);

        try {
            $pending = $this->readPendingActivation($root);
            if ($pending === null) {
                return new UpdateExecutionResult(UpdateExecutionResult::FAILED, 'none', null, [
                    'phase' => 'host_confirmation', 'reason' => 'pending_activation_missing',
                ]);
            }

            $releaseId = $pending['release_id'];
            $releaseSha = $pending['release_sha'];
            $current = $pending['current'];
            if (! is_dir($current) || ! $this->live->liveContains($releaseSha)) {
                return new UpdateExecutionResult(UpdateExecutionResult::REQUIRES_HOST_ACTION, $releaseId, $pending['previous'], [
                    'phase' => 'host_confirmation', 'pending_confirmation' => true, 'reason' => 'live_release_not_ready',
                ]);
            }
            if (! $this->live->runtimeHealthy($releaseSha)) {
                return new UpdateExecutionResult(UpdateExecutionResult::REQUIRES_HOST_ACTION, $releaseId, $pending['previous'], [
                    'phase' => 'host_confirmation', 'pending_confirmation' => true, 'reason' => 'runtime_not_converged',
                ]);
            }
            if (! $this->health->healthy($current, $releaseSha)) {
                return new UpdateExecutionResult(UpdateExecutionResult::REQUIRES_HOST_ACTION, $releaseId, $pending['previous'], [
                    'phase' => 'host_confirmation', 'pending_confirmation' => true, 'reason' => 'backend_health_not_confirmed',
                ]);
            }

            $this->completeActivation($root, $pending['version'], $releaseSha);

            return new UpdateExecutionResult(UpdateExecutionResult::SUCCESS, $releaseId, $pending['previous'], [
                'phase' => 'complete', 'health_confirmed' => true, 'host_confirmation_completed' => true,
            ]);
        } finally {
            $this->releaseLock($lock);
        }
    }

    /** @return array{release_id:string,release_sha:string}|null */
    public function pendingActivation(string $root): ?array
    {
        $pending = $this->readPendingActivation($root);
        if ($pending === null) {
            return null;
        }

        return ['release_id' => $pending['release_id'], 'release_sha' => $pending['release_sha']];
    }

    private function rollbackCode(string $root, string $current, ?string $previous, string $releaseId): bool
    {
        $failed = $root.DIRECTORY_SEPARATOR.'releases'.DIRECTORY_SEPARATOR.$releaseId.'-failed-'.bin2hex(random_bytes(4));
        if (is_dir($current) && ! @rename($current, $failed)) {
            return false;
        }

        return $previous === null || @rename($previous, $current);
    }

    private function completeActivation(string $root, string $version, string $releaseSha): void
    {
        $release = [
            'version' => $version,
            'release_sha' => strtolower($releaseSha),
            'activated_at' => now()->toIso8601String(),
        ];
        File::put($root.DIRECTORY_SEPARATOR.'release.json', json_encode($release, JSON_THROW_ON_ERROR), true);
        File::ensureDirectoryExists(storage_path('app'));
        File::put(storage_path('app/modrik-release.txt'), strtolower($releaseSha).PHP_EOL, true);
        @chmod(storage_path('app/modrik-release.txt'), 0600);
        $this->deletePendingActivation($root);
    }

    /**
     * @param array{release_id:string,version:string,release_sha:string,current:string,previous:?string,backup_path:string,previous_release_sha:?string,activated_at:string} $pending
     */
    private function writePendingActivation(string $root, array $pending): void
    {
        $path = $this->pendingPath($root);
        File::ensureDirectoryExists(dirname($path), 0700);
        File::put($path, json_encode($pending, JSON_THROW_ON_ERROR), true);
        @chmod($path, 0600);
    }

    /**
     * @return array{release_id:string,version:string,release_sha:string,current:string,previous:?string,backup_path:string,previous_release_sha:?string,activated_at:string}|null
     */
    private function readPendingActivation(string $root): ?array
    {
        $path = $this->pendingPath($root);
        if (! is_readable($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            return null;
        }
        foreach (['release_id', 'version', 'release_sha', 'current', 'backup_path', 'activated_at'] as $key) {
            if (! is_string($decoded[$key] ?? null) || $decoded[$key] === '') {
                return null;
            }
        }
        if (preg_match('/^[0-9a-f]{40}$/', strtolower($decoded['release_sha'])) !== 1) {
            return null;
        }
        $previous = is_string($decoded['previous'] ?? null) && $decoded['previous'] !== '' ? $decoded['previous'] : null;
        $previousReleaseSha = is_string($decoded['previous_release_sha'] ?? null) && preg_match('/^[0-9a-f]{40}$/', strtolower($decoded['previous_release_sha'])) === 1
            ? strtolower($decoded['previous_release_sha'])
            : null;

        return [
            'release_id' => $decoded['release_id'],
            'version' => $decoded['version'],
            'release_sha' => strtolower($decoded['release_sha']),
            'current' => $decoded['current'],
            'previous' => $previous,
            'backup_path' => $decoded['backup_path'],
            'previous_release_sha' => $previousReleaseSha,
            'activated_at' => $decoded['activated_at'],
        ];
    }

    private function deletePendingActivation(string $root): void
    {
        $path = $this->pendingPath($root);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function pendingPath(string $root): string
    {
        return rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'shared'.DIRECTORY_SEPARATOR.'pending-activation.json';
    }

    /** @return resource */
    private function acquireLock(string $root)
    {
        $lock = fopen(rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.update.lock', 'c+');
        if ($lock === false) {
            throw new RuntimeException('concurrent_update');
        }
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('concurrent_update');
        }

        return $lock;
    }

    /** @param resource $lock */
    private function releaseLock($lock): void
    {
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    private function safeRestartReason(RestartResult $restart): ?string
    {
        $reason = $restart->details['reason'] ?? null;

        return is_string($reason) && preg_match('/^[a-z0-9_]{1,80}$/', $reason) === 1 ? $reason : null;
    }

    private function safeCode(string $code, string $fallback): string
    {
        return preg_match('/^[a-z0-9_]{1,80}$/', $code) === 1 ? $code : $fallback;
    }
}

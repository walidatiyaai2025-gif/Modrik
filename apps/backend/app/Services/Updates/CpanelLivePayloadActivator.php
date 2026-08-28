<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\File;
use RuntimeException;

final class CpanelLivePayloadActivator
{
    /**
     * @return array{backup_path:string,previous_release_sha:?string}
     */
    public function activate(string $releasePath, string $runtimeRoot, string $releaseId, string $releaseSha): array
    {
        $releaseSha = strtolower($releaseSha);
        if (preg_match('/^[0-9a-f]{40}$/', $releaseSha) !== 1) {
            throw new RuntimeException('invalid_release_sha');
        }

        $sourceBackend = $releasePath.DIRECTORY_SEPARATOR.'payload'.DIRECTORY_SEPARATOR.'backend';
        $sourceWeb = $releasePath.DIRECTORY_SEPARATOR.'payload'.DIRECTORY_SEPARATOR.'web';
        if (! is_file($sourceBackend.DIRECTORY_SEPARATOR.'artisan')
            || ! is_file($sourceBackend.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php')
            || ! is_file($sourceWeb.DIRECTORY_SEPARATOR.'server.js')
            || ! is_file($sourceWeb.DIRECTORY_SEPARATOR.'RELEASE_SHA.txt')) {
            throw new RuntimeException('live_payload_missing');
        }

        $packagedWebSha = strtolower(trim((string) file_get_contents($sourceWeb.DIRECTORY_SEPARATOR.'RELEASE_SHA.txt')));
        if (! hash_equals($releaseSha, $packagedWebSha)) {
            throw new RuntimeException('live_payload_sha_mismatch');
        }

        $backendRoot = $this->backendRoot();
        $webRoot = $this->webRoot();
        $this->assertLiveRoot($backendRoot, 'artisan', 'live_backend_root_invalid');
        $this->assertLiveRoot($webRoot, 'server.js', 'live_web_root_invalid');

        $backupPath = rtrim($runtimeRoot, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'live-backups'
            .DIRECTORY_SEPARATOR.$this->safeReleaseId($releaseId).'-'.bin2hex(random_bytes(6));
        $backupBackend = $backupPath.DIRECTORY_SEPARATOR.'backend';
        $backupWeb = $backupPath.DIRECTORY_SEPARATOR.'web';
        File::ensureDirectoryExists($backupBackend, 0700);
        File::ensureDirectoryExists($backupWeb, 0700);

        $releaseIdentity = storage_path('app/modrik-release.txt');
        $previousReleaseSha = is_readable($releaseIdentity)
            ? strtolower(trim((string) file_get_contents($releaseIdentity)))
            : null;
        if (! is_string($previousReleaseSha) || preg_match('/^[0-9a-f]{40}$/', $previousReleaseSha) !== 1) {
            $previousReleaseSha = null;
        }

        $mutated = false;
        try {
            $this->copyTree($backendRoot, $backupBackend, ['.env', 'storage']);
            $this->copyTree($webRoot, $backupWeb, ['tmp', 'stderr.log']);

            $mutated = true;
            $this->clearTopLevel($backendRoot, ['.env', 'storage']);
            $this->copyTree($sourceBackend, $backendRoot, ['.env', 'storage', 'public/storage', 'public/uploads']);
            $this->restoreBackendSharedLinks($backendRoot);

            $this->clearTopLevel($webRoot, ['.htaccess']);
            $this->copyTree($sourceWeb, $webRoot, ['.htaccess']);
            File::ensureDirectoryExists($webRoot.DIRECTORY_SEPARATOR.'tmp');

            File::ensureDirectoryExists(dirname($releaseIdentity));
            File::put($releaseIdentity, $releaseSha.PHP_EOL, true);
            @chmod($releaseIdentity, 0600);
            File::put($backendRoot.DIRECTORY_SEPARATOR.'RELEASE_SHA.txt', $releaseSha.PHP_EOL, true);

            if (! $this->liveContains($releaseSha)) {
                throw new RuntimeException('live_payload_verification_failed');
            }

            return ['backup_path' => $backupPath, 'previous_release_sha' => $previousReleaseSha];
        } catch (\Throwable $exception) {
            if ($mutated) {
                $this->rollback($backupPath, $previousReleaseSha);
            }

            throw $exception;
        }
    }

    public function liveContains(string $releaseSha): bool
    {
        $releaseSha = strtolower($releaseSha);
        if (preg_match('/^[0-9a-f]{40}$/', $releaseSha) !== 1) {
            return false;
        }

        $backendRoot = $this->backendRoot();
        $webRoot = $this->webRoot();
        $backendIdentity = storage_path('app/modrik-release.txt');
        $webIdentity = $webRoot.DIRECTORY_SEPARATOR.'RELEASE_SHA.txt';

        $backendSha = is_readable($backendIdentity) ? strtolower(trim((string) file_get_contents($backendIdentity))) : '';
        $webSha = is_readable($webIdentity) ? strtolower(trim((string) file_get_contents($webIdentity))) : '';

        return is_file($backendRoot.DIRECTORY_SEPARATOR.'artisan')
            && is_file($backendRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php')
            && is_file($webRoot.DIRECTORY_SEPARATOR.'server.js')
            && hash_equals($releaseSha, $backendSha)
            && hash_equals($releaseSha, $webSha);
    }

    public function runtimeHealthy(string $releaseSha): bool
    {
        if (! $this->liveContains($releaseSha)) {
            return false;
        }

        $releaseSha = strtolower($releaseSha);
        $shortSha = substr($releaseSha, 0, 12);
        $api = $this->request((string) config('update_center.demo.api_up_url', 'https://api.demo.modrik.org/up'));
        $web = $this->request((string) config('update_center.demo.web_url', 'https://demo.modrik.org/'));
        $student = $this->request((string) config('update_center.demo.student_url', 'https://demo.modrik.org/student'));

        if ($api === null || $api['status'] < 200 || $api['status'] >= 300) {
            return false;
        }
        if ($web === null || $web['status'] < 200 || $web['status'] >= 300
            || ! str_contains($web['body'], 'data-testid="modrik-web-release-badge"')
            || ! str_contains($web['body'], "MODRIK deployed release: {$releaseSha}")
            || ! str_contains($web['body'], "Build {$shortSha}")
            || ! str_contains($web['body'], 'data-testid="modrik-landing-page"')) {
            return false;
        }

        return $student !== null
            && $student['status'] >= 200
            && $student['status'] < 300
            && str_contains($student['body'], 'data-testid="modrik-web-release-badge"')
            && str_contains($student['body'], "MODRIK deployed release: {$releaseSha}")
            && str_contains($student['body'], "Build {$shortSha}")
            && str_contains($student['body'], 'data-testid="modrik-student-portal"');
    }

    public function rollback(string $backupPath, ?string $previousReleaseSha): bool
    {
        $backupBackend = rtrim($backupPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'backend';
        $backupWeb = rtrim($backupPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'web';
        if (! is_dir($backupBackend) || ! is_dir($backupWeb)) {
            return false;
        }

        try {
            $backendRoot = $this->backendRoot();
            $webRoot = $this->webRoot();

            $this->clearTopLevel($backendRoot, ['.env', 'storage']);
            $this->copyTree($backupBackend, $backendRoot);
            $this->restoreBackendSharedLinks($backendRoot);

            $this->clearTopLevel($webRoot, ['.htaccess']);
            $this->copyTree($backupWeb, $webRoot, ['.htaccess']);
            File::ensureDirectoryExists($webRoot.DIRECTORY_SEPARATOR.'tmp');

            $identity = storage_path('app/modrik-release.txt');
            if (is_string($previousReleaseSha) && preg_match('/^[0-9a-f]{40}$/', $previousReleaseSha) === 1) {
                File::put($identity, strtolower($previousReleaseSha).PHP_EOL, true);
                @chmod($identity, 0600);
            } elseif (is_file($identity)) {
                @unlink($identity);
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{status:int,body:string}|null */
    private function request(string $url): ?array
    {
        if (! extension_loaded('curl') || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $curl = curl_init();
        if ($curl === false) {
            return null;
        }
        $separator = str_contains($url, '?') ? '&' : '?';
        curl_setopt_array($curl, [
            CURLOPT_URL => $url.$separator.'modrik_update_verify='.rawurlencode(bin2hex(random_bytes(6))),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => max(6, min(20, (int) config('update_center.demo.health_timeout_seconds', 8))),
            CURLOPT_NOSIGNAL => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Cache-Control: no-cache, no-store, max-age=0', 'Pragma: no-cache'],
            CURLOPT_USERAGENT => 'MODRIK-Dashboard-Update/1.0',
            CURLOPT_ENCODING => '',
        ]);
        $body = curl_exec($curl);
        if (! is_string($body)) {
            curl_close($curl);

            return null;
        }
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return ['status' => $status, 'body' => $body];
    }

    private function backendRoot(): string
    {
        return rtrim((string) config('updates.live_backend_root', base_path()), DIRECTORY_SEPARATOR);
    }

    private function webRoot(): string
    {
        $default = dirname(base_path()).DIRECTORY_SEPARATOR.'demo.modrik.org';

        return rtrim((string) config('updates.live_web_root', $default), DIRECTORY_SEPARATOR);
    }

    private function assertLiveRoot(string $root, string $marker, string $error): void
    {
        if ($root === '' || ! is_dir($root) || ! is_writable($root) || ! is_file($root.DIRECTORY_SEPARATOR.$marker)) {
            throw new RuntimeException($error);
        }
    }

    /** @param list<string> $preserve */
    private function clearTopLevel(string $root, array $preserve): void
    {
        foreach (new \DirectoryIterator($root) as $entry) {
            if ($entry->isDot() || in_array($entry->getFilename(), $preserve, true)) {
                continue;
            }
            $path = $entry->getPathname();
            if ($entry->isLink() || $entry->isFile()) {
                @unlink($path);
            } elseif ($entry->isDir()) {
                File::deleteDirectory($path);
            }
        }
    }

    /** @param list<string> $skip */
    private function copyTree(string $source, string $destination, array $skip = [], string $relative = ''): void
    {
        File::ensureDirectoryExists($destination);
        foreach (new \DirectoryIterator($source) as $entry) {
            if ($entry->isDot()) {
                continue;
            }

            $nextRelative = ltrim($relative.'/'.$entry->getFilename(), '/');
            if ($this->isSkipped($nextRelative, $skip)) {
                continue;
            }

            $target = $destination.DIRECTORY_SEPARATOR.$entry->getFilename();
            if ($entry->isLink()) {
                continue;
            }
            if ($entry->isDir()) {
                $this->copyTree($entry->getPathname(), $target, $skip, $nextRelative);

                continue;
            }
            if (! @copy($entry->getPathname(), $target)) {
                throw new RuntimeException('live_payload_copy_failed');
            }
            @chmod($target, ($entry->getPerms() & 0777) ?: 0644);
        }
    }

    /** @param list<string> $skip */
    private function isSkipped(string $relative, array $skip): bool
    {
        $relative = str_replace('\\', '/', $relative);
        foreach ($skip as $path) {
            $path = trim(str_replace('\\', '/', $path), '/');
            if ($relative === $path || str_starts_with($relative, $path.'/')) {
                return true;
            }
        }

        return false;
    }

    private function restoreBackendSharedLinks(string $backendRoot): void
    {
        $storage = storage_path();
        $uploads = (string) config('updates.shared_uploads_path', storage_path('app/public'));
        $this->replaceWithLink($backendRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'storage', $storage.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public');
        $this->replaceWithLink($backendRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'uploads', $uploads);
    }

    private function replaceWithLink(string $link, string $target): void
    {
        if (is_link($link) || is_file($link)) {
            @unlink($link);
        } elseif (is_dir($link)) {
            File::deleteDirectory($link);
        }
        File::ensureDirectoryExists(dirname($link));
        if (! @symlink($target, $link)) {
            throw new RuntimeException('live_shared_state_link_failed');
        }
    }

    private function safeReleaseId(string $releaseId): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $releaseId) ?: 'release';

        return substr($safe, 0, 120);
    }
}

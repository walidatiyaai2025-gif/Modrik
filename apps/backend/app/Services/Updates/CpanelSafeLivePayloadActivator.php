<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\File;

final class CpanelSafeLivePayloadActivator implements LivePayloadActivator
{
    public function __construct(private CpanelLivePayloadActivator $inner) {}

    /**
     * @return array{backup_path:string,previous_release_sha:?string}
     */
    public function activate(string $releasePath, string $runtimeRoot, string $releaseId, string $releaseSha): array
    {
        $result = $this->inner->activate($releasePath, $runtimeRoot, $releaseId, $releaseSha);
        $this->clearCandidateConfigCache();

        return $result;
    }

    public function liveContains(string $releaseSha): bool
    {
        return $this->inner->liveContains($releaseSha);
    }

    public function runtimeHealthy(string $releaseSha): bool
    {
        if (! $this->inner->liveContains($releaseSha)) {
            return false;
        }

        $webRoot = rtrim((string) config('updates.live_web_root', dirname(base_path()).DIRECTORY_SEPARATOR.'demo.modrik.org'), DIRECTORY_SEPARATOR);
        $releaseIdentity = $webRoot.DIRECTORY_SEPARATOR.'RELEASE_SHA.txt';
        $restartMarker = $webRoot.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'restart.txt';

        if (! is_file($releaseIdentity) || ! is_file($restartMarker)) {
            return false;
        }

        $releaseMtime = @filemtime($releaseIdentity);
        $restartMtime = @filemtime($restartMarker);

        return is_int($releaseMtime)
            && is_int($restartMtime)
            && $restartMtime >= $releaseMtime;
    }

    public function rollback(string $backupPath, ?string $previousReleaseSha): bool
    {
        return $this->inner->rollback($backupPath, $previousReleaseSha);
    }

    private function clearCandidateConfigCache(): void
    {
        $backendRoot = rtrim((string) config('updates.live_backend_root', base_path()), DIRECTORY_SEPARATOR);
        $cacheDirectory = $backendRoot.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache';
        File::ensureDirectoryExists($cacheDirectory);

        $configCache = $cacheDirectory.DIRECTORY_SEPARATOR.'config.php';
        if (is_file($configCache) && ! @unlink($configCache)) {
            throw new \RuntimeException('live_config_cache_clear_failed');
        }
    }
}

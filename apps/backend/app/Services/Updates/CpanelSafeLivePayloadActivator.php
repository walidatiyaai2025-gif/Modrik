<?php

namespace App\Services\Updates;

final class CpanelSafeLivePayloadActivator implements LivePayloadActivator
{
    public function __construct(private CpanelLivePayloadActivator $inner) {}

    /**
     * @return array{backup_path:string,previous_release_sha:?string}
     */
    public function activate(string $releasePath, string $runtimeRoot, string $releaseId, string $releaseSha): array
    {
        return $this->inner->activate($releasePath, $runtimeRoot, $releaseId, $releaseSha);
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
}

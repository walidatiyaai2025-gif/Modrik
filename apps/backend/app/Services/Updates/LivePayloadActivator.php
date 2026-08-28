<?php

namespace App\Services\Updates;

interface LivePayloadActivator
{
    /**
     * @return array{backup_path:string,previous_release_sha:?string}
     */
    public function activate(string $releasePath, string $runtimeRoot, string $releaseId, string $releaseSha): array;

    public function liveContains(string $releaseSha): bool;

    public function runtimeHealthy(string $releaseSha): bool;

    public function rollback(string $backupPath, ?string $previousReleaseSha): bool;
}

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
    ) {}

    public function install(string $archive, string $root, ?string $currentVersion = null): UpdateExecutionResult
    {
        File::ensureDirectoryExists($root);
        $lock = fopen($root.DIRECTORY_SEPARATOR.'.update.lock', 'c+');
        if ($lock === false) {
            throw new RuntimeException('concurrent_update');
        }
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('concurrent_update');
        }
        $maintenance = $root.DIRECTORY_SEPARATOR.'shared'.DIRECTORY_SEPARATOR.'maintenance.json';
        $staging = null;
        $candidate = null;
        $current = $root.DIRECTORY_SEPARATOR.'current';
        $previous = null;

        try {
            $validation = $this->validator->validate($archive, $currentVersion);
            if (! $validation->valid || ! is_array($validation->manifest)) {
                throw new RuntimeException('package_validation_failed');
            }
            $releaseId = $validation->manifest['version'].'-'.$validation->manifest['release_sha'];
            $releaseSha = (string) $validation->manifest['release_sha'];
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
                } throw new RuntimeException('activation_failed');
            }
            $restart = $this->restart->restart($current);
            if (! $restart->successful()) {
                $rolledBack = $this->rollbackCode($root, $current, $previous, $releaseId);
                $status = $restart->status === 'requires_host_action'
                    ? UpdateExecutionResult::REQUIRES_HOST_ACTION
                    : ($rolledBack ? UpdateExecutionResult::ROLLED_BACK : UpdateExecutionResult::FAILED);

                return new UpdateExecutionResult($status, $releaseId, $previous, [
                    'phase' => 'web_restart', 'code_rolled_back' => $rolledBack, 'restart_status' => $restart->status,
                    'restart_reason' => $this->safeRestartReason($restart),
                ]);
            }

            if (! $this->health->healthy($current, $releaseSha)) {
                $rolledBack = $this->rollbackCode($root, $current, $previous, $releaseId);

                return new UpdateExecutionResult($rolledBack ? UpdateExecutionResult::ROLLED_BACK : UpdateExecutionResult::FAILED, $releaseId, $previous, [
                    'phase' => 'activation_health', 'code_rolled_back' => $rolledBack,
                ]);
            }

            File::put($root.DIRECTORY_SEPARATOR.'release.json', json_encode([
                'version' => $validation->manifest['version'], 'release_sha' => strtolower($releaseSha), 'activated_at' => now()->toIso8601String(),
            ], JSON_THROW_ON_ERROR), true);

            return new UpdateExecutionResult(UpdateExecutionResult::SUCCESS, $releaseId, $previous, [
                'phase' => 'complete', 'health_confirmed' => true,
            ]);
        } finally {
            if (is_file($maintenance)) {
                @unlink($maintenance);
            }
            if (is_string($staging) && is_dir($staging)) {
                File::deleteDirectory($staging);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function rollbackCode(string $root, string $current, ?string $previous, string $releaseId): bool
    {
        $failed = $root.DIRECTORY_SEPARATOR.'releases'.DIRECTORY_SEPARATOR.$releaseId.'-failed-'.bin2hex(random_bytes(4));
        if (is_dir($current) && ! @rename($current, $failed)) {
            return false;
        }

        return $previous === null || @rename($previous, $current);
    }

    private function safeRestartReason(RestartResult $restart): ?string
    {
        $reason = $restart->details['reason'] ?? null;

        return is_string($reason) && preg_match('/^[a-z0-9_]{1,80}$/', $reason) === 1 ? $reason : null;
    }
}

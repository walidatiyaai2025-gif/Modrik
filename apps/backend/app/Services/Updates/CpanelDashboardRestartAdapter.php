<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

final class CpanelDashboardRestartAdapter implements WebRestartAdapter
{
    public function __construct(private LivePayloadActivator $live) {}

    public function restart(string $releasePath): RestartResult
    {
        try {
            $releaseSha = $this->releaseSha($releasePath);
        } catch (Throwable $exception) {
            return RestartResult::failed('The staged release does not satisfy the cPanel restart contract.', [
                'reason' => $this->safeReason($exception->getMessage(), 'candidate_invalid'),
            ]);
        }

        if (! $this->live->liveContains($releaseSha)) {
            return RestartResult::requiresHostAction(
                'The live cPanel roots do not yet contain the candidate release.',
                ['reason' => 'live_payload_activation_required', 'release_sha' => $releaseSha],
            );
        }

        $webRoot = rtrim((string) config('updates.live_web_root', dirname(base_path()).DIRECTORY_SEPARATOR.'demo.modrik.org'), DIRECTORY_SEPARATOR);
        $restartDirectory = $webRoot.DIRECTORY_SEPARATOR.'tmp';
        try {
            File::ensureDirectoryExists($restartDirectory);
            if (! @touch($restartDirectory.DIRECTORY_SEPARATOR.'restart.txt')) {
                throw new RuntimeException('restart_marker_unavailable');
            }
        } catch (Throwable) {
            return RestartResult::requiresHostAction(
                'The live release is active, but the application could not place the cPanel restart marker.',
                ['reason' => 'restart_marker_unavailable', 'release_sha' => $releaseSha],
            );
        }

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            if ($attempt > 1) {
                sleep(2);
            }
            if ($this->live->runtimeHealthy($releaseSha)) {
                return RestartResult::success(
                    'The live cPanel runtime converged to the candidate release after the restart marker.',
                    ['release_sha' => $releaseSha, 'verification_attempts' => $attempt],
                );
            }
        }

        return RestartResult::requiresHostAction(
            'The code is active, but the Node runtime still needs a cPanel Restart. Restart the demo.modrik.org Node application, then use Verify & Complete in MODRIK.',
            [
                'reason' => 'cpanel_restart_confirmation_required',
                'release_sha' => $releaseSha,
                'restart_marker_written' => true,
            ],
        );
    }

    private function releaseSha(string $releasePath): string
    {
        $manifest = json_decode((string) @file_get_contents(rtrim($releasePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'manifest.json'), true);
        $releaseSha = is_array($manifest) && is_string($manifest['release_sha'] ?? null)
            ? strtolower(trim($manifest['release_sha']))
            : '';
        if (preg_match('/^[0-9a-f]{40}$/', $releaseSha) !== 1) {
            throw new RuntimeException('candidate_release_identity_invalid');
        }

        return $releaseSha;
    }

    private function safeReason(string $reason, string $fallback): string
    {
        return preg_match('/^[a-z0-9_]{1,80}$/', $reason) === 1 ? $reason : $fallback;
    }
}

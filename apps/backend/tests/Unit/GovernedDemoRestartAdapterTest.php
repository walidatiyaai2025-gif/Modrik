<?php

namespace Tests\Unit;

use App\Services\Updates\CpanelDashboardRestartAdapter;
use App\Services\Updates\LivePayloadActivator;
use App\Services\Updates\RestartResult;
use App\Services\Updates\WebRestartAdapter;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

final class GovernedDemoRestartAdapterTest extends TestCase
{
    public function test_container_binds_dashboard_cpanel_restart_adapter(): void
    {
        $this->assertInstanceOf(CpanelDashboardRestartAdapter::class, app(WebRestartAdapter::class));
    }

    public function test_live_payload_must_be_active_before_restart_marker_is_written(): void
    {
        $releaseSha = str_repeat('a', 40);
        $release = $this->candidateRelease($releaseSha);
        $webRoot = $this->webRoot();
        config(['updates.live_web_root' => $webRoot]);
        $adapter = new CpanelDashboardRestartAdapter($this->liveActivator(false));

        $result = $adapter->restart($release);

        $this->assertSame(RestartResult::STATUS_REQUIRES_HOST_ACTION, $result->status);
        $this->assertSame('live_payload_activation_required', $result->details['reason'] ?? null);
        $this->assertFileDoesNotExist($webRoot.'/tmp/restart.txt');
    }

    public function test_verified_live_payload_writes_marker_and_requires_explicit_cpanel_confirmation(): void
    {
        $releaseSha = str_repeat('b', 40);
        $release = $this->candidateRelease($releaseSha);
        $webRoot = $this->webRoot();
        config(['updates.live_web_root' => $webRoot]);
        $adapter = new CpanelDashboardRestartAdapter($this->liveActivator(true));

        $result = $adapter->restart($release);

        $this->assertSame(RestartResult::STATUS_REQUIRES_HOST_ACTION, $result->status);
        $this->assertSame('cpanel_restart_confirmation_required', $result->details['reason'] ?? null);
        $this->assertSame($releaseSha, $result->details['release_sha'] ?? null);
        $this->assertTrue($result->details['restart_marker_written'] ?? false);
        $this->assertFileExists($webRoot.'/tmp/restart.txt');
    }

    private function liveActivator(bool $contains): LivePayloadActivator
    {
        return new class($contains) implements LivePayloadActivator
        {
            public function __construct(private bool $contains) {}

            public function activate(string $releasePath, string $runtimeRoot, string $releaseId, string $releaseSha): array
            {
                return ['backup_path' => $runtimeRoot.'/backup', 'previous_release_sha' => null];
            }

            public function liveContains(string $releaseSha): bool
            {
                return $this->contains;
            }

            public function runtimeHealthy(string $releaseSha): bool
            {
                throw new RuntimeException('cPanel restart adapter must not self-poll runtime health');
            }

            public function rollback(string $backupPath, ?string $previousReleaseSha): bool
            {
                return true;
            }
        };
    }

    private function candidateRelease(string $releaseSha): string
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modrik-restart-candidate-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($root);
        File::put($root.DIRECTORY_SEPARATOR.'manifest.json', json_encode([
            'release_sha' => $releaseSha,
        ], JSON_THROW_ON_ERROR));
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($root));

        return $root;
    }

    private function webRoot(): string
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modrik-live-web-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($root);
        File::put($root.DIRECTORY_SEPARATOR.'server.js', "module.exports = {};\n");
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($root));

        return $root;
    }
}

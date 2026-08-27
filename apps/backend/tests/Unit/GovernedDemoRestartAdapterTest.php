<?php

namespace Tests\Unit;

use App\Services\Updates\GovernedDemoRestartAdapter;
use App\Services\Updates\RestartResult;
use App\Services\Updates\WebRestartAdapter;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class GovernedDemoRestartAdapterTest extends TestCase
{
    public function test_container_binds_governed_restart_adapter(): void
    {
        $this->assertInstanceOf(GovernedDemoRestartAdapter::class, app(WebRestartAdapter::class));
    }

    public function test_disabled_hosting_bridge_requires_host_action_without_mutation(): void
    {
        $release = $this->candidateRelease(str_repeat('a', 40));
        config(['update_center.demo.hosting_bridge_enabled' => false]);

        $result = app(WebRestartAdapter::class)->restart($release);

        $this->assertSame(RestartResult::STATUS_REQUIRES_HOST_ACTION, $result->status);
        $this->assertSame('hosting_bridge_disabled', $result->details['reason'] ?? null);
    }

    public function test_stale_fixed_demo_root_requires_host_action_before_restart(): void
    {
        $releaseSha = str_repeat('b', 40);
        $release = $this->candidateRelease($releaseSha);
        $liveRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modrik-live-web-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($liveRoot);
        File::put($liveRoot.DIRECTORY_SEPARATOR.'RELEASE_SHA.txt', str_repeat('c', 40)."\n");
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($liveRoot));

        config([
            'update_center.demo.hosting_bridge_enabled' => true,
            'update_center.demo.web_root' => $liveRoot,
            'update_center.demo.node_app_root' => 'public_html/demo.modrik.org',
            'update_center.demo.domain' => 'demo.modrik.org',
            'update_center.demo.origin_ip' => '65.21.208.232',
            'update_center.demo.node_major' => 22,
        ]);

        $result = app(WebRestartAdapter::class)->restart($release);

        $this->assertSame(RestartResult::STATUS_REQUIRES_HOST_ACTION, $result->status);
        $this->assertSame('live_payload_activation_required', $result->details['reason'] ?? null);
        $this->assertSame($releaseSha, $result->details['release_sha'] ?? null);
    }

    private function candidateRelease(string $releaseSha): string
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modrik-restart-candidate-'.bin2hex(random_bytes(8));
        $web = $root.DIRECTORY_SEPARATOR.'payload'.DIRECTORY_SEPARATOR.'web';
        File::ensureDirectoryExists($web);
        File::put($root.DIRECTORY_SEPARATOR.'manifest.json', json_encode([
            'release_sha' => $releaseSha,
        ], JSON_THROW_ON_ERROR));
        File::put($web.DIRECTORY_SEPARATOR.'RELEASE_SHA.txt', $releaseSha."\n");
        File::put($web.DIRECTORY_SEPARATOR.'WEB_APPLICATION_ROOT.txt', ".\n");
        File::put($web.DIRECTORY_SEPARATOR.'server.js', "require('./apps/web/server.js');\n");
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($root));

        return $root;
    }
}

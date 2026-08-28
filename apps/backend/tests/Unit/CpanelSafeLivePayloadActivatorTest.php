<?php

namespace Tests\Unit;

use App\Services\Updates\CpanelSafeLivePayloadActivator;
use App\Services\Updates\LivePayloadActivator;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class CpanelSafeLivePayloadActivatorTest extends TestCase
{
    public function test_container_binds_cpanel_safe_live_payload_activator(): void
    {
        $this->assertInstanceOf(CpanelSafeLivePayloadActivator::class, app(LivePayloadActivator::class));
    }

    public function test_runtime_health_is_local_and_requires_candidate_restart_marker(): void
    {
        $releaseSha = str_repeat('c', 40);
        $backendRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modrik-safe-backend-'.bin2hex(random_bytes(8));
        $webRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modrik-safe-web-'.bin2hex(random_bytes(8));

        File::ensureDirectoryExists($backendRoot.DIRECTORY_SEPARATOR.'public');
        File::ensureDirectoryExists($webRoot.DIRECTORY_SEPARATOR.'tmp');
        File::put($backendRoot.DIRECTORY_SEPARATOR.'artisan', "<?php\n");
        File::put($backendRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php', "<?php\n");
        File::put($backendRoot.DIRECTORY_SEPARATOR.'RELEASE_SHA.txt', $releaseSha.PHP_EOL);
        File::put($webRoot.DIRECTORY_SEPARATOR.'server.js', "module.exports = {};\n");
        File::put($webRoot.DIRECTORY_SEPARATOR.'RELEASE_SHA.txt', $releaseSha.PHP_EOL);

        config([
            'updates.live_backend_root' => $backendRoot,
            'updates.live_web_root' => $webRoot,
        ]);

        $this->beforeApplicationDestroyed(function () use ($backendRoot, $webRoot): void {
            File::deleteDirectory($backendRoot);
            File::deleteDirectory($webRoot);
        });

        $safe = app(LivePayloadActivator::class);

        $this->assertFalse($safe->runtimeHealthy($releaseSha));

        usleep(10000);
        File::put($webRoot.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'restart.txt', 'restart');
        clearstatcache(true, $webRoot.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'restart.txt');

        $this->assertTrue($safe->runtimeHealthy($releaseSha));
    }

    public function test_activation_removes_candidate_config_cache_from_live_backend(): void
    {
        $releaseSha = str_repeat('d', 40);
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modrik-safe-activation-'.bin2hex(random_bytes(8));
        $releasePath = $root.DIRECTORY_SEPARATOR.'release';
        $runtimeRoot = $root.DIRECTORY_SEPARATOR.'runtime';
        $backendRoot = $root.DIRECTORY_SEPARATOR.'live-backend';
        $webRoot = $root.DIRECTORY_SEPARATOR.'live-web';
        $sourceBackend = $releasePath.DIRECTORY_SEPARATOR.'payload'.DIRECTORY_SEPARATOR.'backend';
        $sourceWeb = $releasePath.DIRECTORY_SEPARATOR.'payload'.DIRECTORY_SEPARATOR.'web';

        File::ensureDirectoryExists($sourceBackend.DIRECTORY_SEPARATOR.'public');
        File::ensureDirectoryExists($sourceBackend.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache');
        File::ensureDirectoryExists($sourceWeb);
        File::ensureDirectoryExists($backendRoot.DIRECTORY_SEPARATOR.'public');
        File::ensureDirectoryExists($backendRoot.DIRECTORY_SEPARATOR.'storage');
        File::ensureDirectoryExists($webRoot);
        File::ensureDirectoryExists($runtimeRoot);

        File::put($sourceBackend.DIRECTORY_SEPARATOR.'artisan', "<?php\n");
        File::put($sourceBackend.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php', "<?php\n");
        File::put(
            $sourceBackend.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'config.php',
            "<?php return ['view' => ['paths' => ['/candidate/release/resources/views']]];\n",
        );
        File::put($sourceWeb.DIRECTORY_SEPARATOR.'server.js', "module.exports = {};\n");
        File::put($sourceWeb.DIRECTORY_SEPARATOR.'RELEASE_SHA.txt', $releaseSha.PHP_EOL);

        File::put($backendRoot.DIRECTORY_SEPARATOR.'artisan', "<?php\n");
        File::put($backendRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php', "<?php\n");
        File::put($webRoot.DIRECTORY_SEPARATOR.'server.js', "module.exports = {};\n");

        config([
            'updates.live_backend_root' => $backendRoot,
            'updates.live_web_root' => $webRoot,
        ]);

        $this->beforeApplicationDestroyed(function () use ($root): void {
            File::deleteDirectory($root);
        });

        $safe = app(LivePayloadActivator::class);
        $safe->activate($releasePath, $runtimeRoot, 'test-release', $releaseSha);

        $this->assertDirectoryExists($backendRoot.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache');
        $this->assertFileDoesNotExist($backendRoot.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'config.php');
        $this->assertTrue($safe->liveContains($releaseSha));
    }
}

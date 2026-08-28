<?php

namespace Tests\Unit;

use App\Services\Updates\GovernedActivationHealthChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class GovernedActivationHealthCheckerTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_uses_database_migration_parity_without_process_execution(): void
    {
        $releaseSha = str_repeat('d', 40);
        $releaseRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modrik-health-release-'.bin2hex(random_bytes(8));
        $backend = $releaseRoot.DIRECTORY_SEPARATOR.'payload'.DIRECTORY_SEPARATOR.'backend';
        $web = $releaseRoot.DIRECTORY_SEPARATOR.'payload'.DIRECTORY_SEPARATOR.'web';
        $migrationName = '2099_01_01_000000_cpanel_health_test';

        File::ensureDirectoryExists($backend.DIRECTORY_SEPARATOR.'public');
        File::ensureDirectoryExists($backend.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations');
        File::ensureDirectoryExists($web);
        File::put($backend.DIRECTORY_SEPARATOR.'artisan', "<?php\n");
        File::put($backend.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php', "<?php\n");
        File::put($backend.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.$migrationName.'.php', "<?php\n");
        File::put($web.DIRECTORY_SEPARATOR.'server.js', "module.exports = {};\n");
        File::put($web.DIRECTORY_SEPARATOR.'RELEASE_SHA.txt', $releaseSha.PHP_EOL);
        config(['app.debug' => false]);

        DB::table('migrations')->insert(['migration' => $migrationName, 'batch' => 999]);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($releaseRoot));

        $checker = app(GovernedActivationHealthChecker::class);
        $this->assertTrue($checker->healthy($releaseRoot, $releaseSha));

        File::put(
            $backend.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'2099_01_01_000001_missing.php',
            "<?php\n",
        );

        $this->assertFalse($checker->healthy($releaseRoot, $releaseSha));
    }
}

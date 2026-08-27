<?php

namespace Tests\Unit;

use App\Services\Updates\ActivationHealthChecker;
use App\Services\Updates\RestartResult;
use App\Services\Updates\TransactionalReleaseManager;
use App\Services\Updates\UnifiedPackageValidator;
use App\Services\Updates\UpdateExecutionResult;
use App\Services\Updates\WebRestartAdapter;
use RuntimeException;
use Tests\Support\CreatesUnifiedUpdatePackage;
use Tests\Support\RecordingBackendReleaseOperator;
use Tests\TestCase;

final class TransactionalReleaseManagerTest extends TestCase
{
    use CreatesUnifiedUpdatePackage;

    public function test_n_to_n_plus_one_preserves_shared_state_and_activates_only_after_health(): void
    {
        [$root, $manager, $backend] = $this->harness();
        $result = $manager->install($this->unifiedUpdatePackage(), $root, '1.0.0');

        $this->assertSame(UpdateExecutionResult::SUCCESS, $result->status);
        $this->assertSame(['migrate', 'cache'], $backend->calls);
        $this->assertSame('shared-data', file_get_contents($root.'/shared/uploads/user.txt'));
        $this->assertFileExists($root.'/current/payload/web/server.js');
        $this->assertFileDoesNotExist($root.'/shared/maintenance.json');
        $this->assertStringContainsString(str_repeat('a', 40), (string) file_get_contents($root.'/release.json'));
    }

    public function test_failed_migration_never_activates_code_or_attempts_database_down(): void
    {
        [$root, $manager] = $this->harness(migrate: false);
        $result = $manager->install($this->unifiedUpdatePackage(), $root, '1.0.0');

        $this->assertSame(UpdateExecutionResult::PARTIAL_REQUIRES_OPERATOR, $result->status);
        $this->assertSame('old', file_get_contents($root.'/current/marker.txt'));
        $this->assertFalse($result->details['database_rollback_attempted']);
    }

    public function test_unconfirmed_restart_restores_previous_code_and_requires_host_action(): void
    {
        [$root, $manager] = $this->harness(restart: 'requires_host_action');
        $result = $manager->install($this->unifiedUpdatePackage(), $root, '1.0.0');

        $this->assertSame(UpdateExecutionResult::REQUIRES_HOST_ACTION, $result->status);
        $this->assertTrue($result->details['code_rolled_back']);
        $this->assertSame('old', file_get_contents($root.'/current/marker.txt'));
    }

    public function test_failed_health_check_rolls_code_back(): void
    {
        [$root, $manager] = $this->harness(healthy: false);
        $result = $manager->install($this->unifiedUpdatePackage(), $root, '1.0.0');

        $this->assertSame(UpdateExecutionResult::ROLLED_BACK, $result->status);
        $this->assertSame('old', file_get_contents($root.'/current/marker.txt'));
    }

    public function test_concurrent_update_is_rejected_before_validation_or_mutation(): void
    {
        [$root, $manager] = $this->harness();
        $lock = fopen($root.'/.update.lock', 'c+');
        $this->assertIsResource($lock);
        flock($lock, LOCK_EX | LOCK_NB);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('concurrent_update');
            $manager->install($this->unifiedUpdatePackage(), $root, '1.0.0');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array{string,TransactionalReleaseManager,RecordingBackendReleaseOperator} */
    private function harness(bool $migrate = true, bool $healthy = true, string $restart = 'succeeded'): array
    {
        $root = sys_get_temp_dir().'/modrik-runtime-'.bin2hex(random_bytes(8));
        mkdir($root.'/current', 0700, true);
        mkdir($root.'/shared/uploads', 0700, true);
        file_put_contents($root.'/current/marker.txt', 'old');
        file_put_contents($root.'/shared/uploads/user.txt', 'shared-data');
        $this->beforeApplicationDestroyed(fn () => is_dir($root) ? $this->deleteDirectory($root) : null);

        $backend = new RecordingBackendReleaseOperator($migrate);
        $restartAdapter = new class($restart) implements WebRestartAdapter
        {
            public function __construct(private string $status) {}

            public function restart(string $releasePath): RestartResult
            {
                return new RestartResult($this->status, 'test adapter');
            }
        };
        $health = new class($healthy) implements ActivationHealthChecker
        {
            public function __construct(private bool $result) {}

            public function healthy(string $releasePath, string $expectedReleaseSha): bool
            {
                return $this->result;
            }
        };

        return [$root, new TransactionalReleaseManager(app(UnifiedPackageValidator::class), $restartAdapter, $backend, $health), $backend];
    }

    private function deleteDirectory(string $directory): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}

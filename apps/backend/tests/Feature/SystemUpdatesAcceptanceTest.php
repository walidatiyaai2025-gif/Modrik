<?php

namespace Tests\Feature;

use App\Filament\Pages\SystemUpdates;
use App\Models\User;
use App\Services\Updates\ActivationHealthChecker;
use App\Services\Updates\BackendReleaseOperator;
use App\Services\Updates\RestartResult;
use App\Services\Updates\TransactionalReleaseManager;
use App\Services\Updates\UnifiedPackageValidator;
use App\Services\Updates\WebRestartAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CreatesUnifiedUpdatePackage;
use Tests\TestCase;
use ZipArchive;

final class SystemUpdatesAcceptanceTest extends TestCase
{
    use CreatesUnifiedUpdatePackage;
    use RefreshDatabase;

    public function test_only_active_admin_can_access_update_center(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'account_status' => 'active']);

        $this->actingAs($admin)->get('/admin/system-updates')->assertOk();
        $this->actingAs($student)->get('/admin/system-updates')->assertForbidden();
    }

    public function test_admin_validates_private_package_installs_it_and_records_terminal_history(): void
    {
        Storage::fake('local');
        config(['app.version' => '1.0.0', 'updates.upload_disk' => 'local']);
        $runtime = sys_get_temp_dir().'/modrik-ui-update-'.bin2hex(random_bytes(8));
        config(['updates.runtime_root' => $runtime]);
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $manager = new TransactionalReleaseManager(
            app(UnifiedPackageValidator::class),
            new class implements WebRestartAdapter
            {
                public function restart(string $releasePath): RestartResult
                {
                    return new RestartResult('succeeded', 'confirmed by acceptance adapter');
                }
            },
            new class implements BackendReleaseOperator
            {
                public function prepareSharedState(string $releasePath, string $sharedPath): void {}

                public function migrate(string $releasePath): bool
                {
                    return true;
                }

                public function rebuildCaches(string $releasePath): bool
                {
                    return true;
                }
            },
            new class implements ActivationHealthChecker
            {
                public function healthy(string $releasePath, string $expectedReleaseSha): bool
                {
                    return is_file($releasePath.'/payload/web/server.js');
                }
            },
        );
        $this->app->instance(TransactionalReleaseManager::class, $manager);
        $archive = $this->unifiedUpdatePackage();

        $upload = $this->actingAs($admin)->post(route('system-updates.upload-package'), [
            'package' => UploadedFile::fake()->createWithContent('release.zip', (string) file_get_contents($archive)),
        ]);
        $upload->assertRedirect(SystemUpdates::getUrl());
        $upload->assertSessionHas('modrik.update.validation_result', fn (array $result): bool => ($result['valid'] ?? false) === true);
        $upload->assertSessionHas('modrik.update.validated_update_id');

        Livewire::actingAs($admin)->test(app(SystemUpdates::class))
            ->assertSet('validationResult.valid', true)
            ->call('installUpdate')
            ->assertSet('installationResult.status', 'SUCCESS');

        $this->assertDatabaseHas('system_update_history', ['initiated_by' => $admin->id, 'status' => 'SUCCESS', 'to_version' => '1.1.0']);
        $this->assertFileExists($runtime.'/current/payload/web/server.js');
        $this->assertDatabaseMissing('system_update_history', ['safe_details' => 'DatabaseSecret1']);
        $this->deleteDirectory($runtime);
    }

    public function test_corrupt_package_is_rejected_before_runtime_mutation(): void
    {
        Storage::fake('local');
        config(['app.version' => '1.0.0', 'updates.upload_disk' => 'local']);
        $runtime = sys_get_temp_dir().'/modrik-ui-update-'.bin2hex(random_bytes(8));
        config(['updates.runtime_root' => $runtime]);
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $archive = $this->unifiedUpdatePackage();
        $zip = new ZipArchive;
        $zip->open($archive);
        $zip->addFromString('payload/backend/artisan', 'tampered');
        $zip->close();

        $upload = $this->actingAs($admin)->post(route('system-updates.upload-package'), [
            'package' => UploadedFile::fake()->createWithContent('corrupt.zip', (string) file_get_contents($archive)),
        ]);
        $upload->assertRedirect(SystemUpdates::getUrl());
        $upload->assertSessionHas('modrik.update.validation_result', fn (array $result): bool => ($result['valid'] ?? true) === false);
        $upload->assertSessionMissing('modrik.update.validated_update_id');

        Livewire::actingAs($admin)->test(app(SystemUpdates::class))
            ->assertSet('validationResult.valid', false)
            ->assertSet('validatedUpdateId', null);

        $this->assertDatabaseHas('system_update_history', ['status' => 'validation_failed']);
        $this->assertDirectoryDoesNotExist($runtime);
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}

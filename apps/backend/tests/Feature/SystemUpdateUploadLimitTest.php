<?php

namespace Tests\Feature;

use App\Filament\Pages\SystemUpdates;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class SystemUpdateUploadLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_center_accepts_valid_package_larger_than_livewire_default_limit(): void
    {
        Storage::fake('local');
        config()->set('updates.upload_disk', 'local');
        config()->set('updates.max_package_kb', 131072);
        config()->set('app.version', '0.1.0');

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'modrik-update-');
        $this->assertIsString($path);
        $zipPath = $path.'.zip';
        rename($path, $zipPath);

        $payloadPath = 'payload/web/large.bin';
        $payload = str_repeat('A', 13 * 1024 * 1024);
        $manifest = [
            'package_format_version' => 1,
            'product' => 'MODRIK',
            'release_sha' => str_repeat('a', 40),
            'version' => '0.1.1',
            'minimum_compatible_version' => '0.1.0',
            'runtime' => [
                'php' => '8.4',
                'node' => '22',
                'database' => 'mariadb-10.11+',
            ],
        ];

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $zip->addFromString('checksums.json', json_encode([
            $payloadPath => hash('sha256', $payload),
        ], JSON_THROW_ON_ERROR));
        $zip->addFromString($payloadPath, $payload);
        $zip->setCompressionName($payloadPath, ZipArchive::CM_STORE);
        $zip->close();

        $this->assertGreaterThan(12 * 1024 * 1024, filesize($zipPath));

        try {
            $response = $this->actingAs($admin)->post(route('system-updates.upload-package'), [
                'package' => new UploadedFile($zipPath, 'modrik-release-0.1.1.zip', 'application/zip', null, true),
            ]);

            $response->assertRedirect(SystemUpdates::getUrl());
            $response->assertSessionHas('modrik.update.validation_result', function (array $result): bool {
                return ($result['valid'] ?? false) === true
                    && ($result['manifest']['version'] ?? null) === '0.1.1';
            });
            $response->assertSessionHas('modrik.update.validated_update_id');

            $this->assertDatabaseHas('system_update_history', [
                'initiated_by' => $admin->id,
                'to_version' => '0.1.1',
                'release_sha' => str_repeat('a', 40),
                'status' => 'validated',
            ]);
        } finally {
            @unlink($zipPath);
        }
    }
}

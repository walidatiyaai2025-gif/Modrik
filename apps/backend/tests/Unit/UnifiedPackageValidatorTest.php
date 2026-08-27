<?php

namespace Tests\Unit;

use App\Services\Updates\UnifiedPackageValidator;
use Tests\TestCase;
use ZipArchive;

final class UnifiedPackageValidatorTest extends TestCase
{
    public function test_valid_package_is_accepted(): void
    {
        $result = app(UnifiedPackageValidator::class)->validate($this->package());
        $this->assertTrue($result->valid, json_encode($result->errors));
    }

    public function test_corrupt_checksum_is_rejected(): void
    {
        $result = app(UnifiedPackageValidator::class)->validate($this->package(['payload/backend/artisan' => 'tampered'], ['payload/backend/artisan' => str_repeat('0', 64)]));
        $this->assertFalse($result->valid);
        $this->assertContains('checksum_mismatch', array_column($result->errors, 'code'));
    }

    public function test_traversal_and_embedded_env_are_rejected(): void
    {
        foreach ([['../escape.php' => 'x'], ['payload/backend/.env' => 'APP_KEY=secret']] as $entries) {
            $result = app(UnifiedPackageValidator::class)->validate($this->package($entries));
            $this->assertFalse($result->valid);
        }
    }

    public function test_incompatible_upgrade_is_rejected(): void
    {
        $result = app(UnifiedPackageValidator::class)->validate($this->package([], [], ['minimum_compatible_version' => '2.0.0']), '1.0.0');
        $this->assertContains('incompatible_upgrade', array_column($result->errors, 'code'));
    }

    /** @param array<string,string> $extra @param array<string,string> $checksumOverrides @param array<string,mixed> $manifestOverrides */
    private function package(array $extra = [], array $checksumOverrides = [], array $manifestOverrides = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'modrik-package-').'.zip';
        $payload = ['payload/web/server.js' => 'web', 'payload/backend/artisan' => 'backend'];
        $manifest = array_merge([
            'package_format_version' => 1, 'product' => 'MODRIK', 'version' => '1.1.0',
            'release_sha' => str_repeat('a', 40), 'minimum_compatible_version' => '1.0.0',
            'runtime' => ['php' => '8.4', 'node' => '22.23.2', 'database' => 'mariadb-10.11+'],
        ], $manifestOverrides);
        $entries = array_merge($payload, ['manifest.json' => json_encode($manifest, JSON_THROW_ON_ERROR)], $extra);
        $checksums = [];
        foreach ($entries as $name => $contents) if ($name === 'manifest.json' || str_starts_with($name, 'payload/')) $checksums[$name] = hash('sha256', $contents);
        $entries['checksums.json'] = json_encode(array_merge($checksums, $checksumOverrides), JSON_THROW_ON_ERROR);
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) $zip->addFromString($name, $contents);
        $zip->close();
        $this->beforeApplicationDestroyed(fn () => @unlink($path));
        return $path;
    }
}

<?php

namespace Tests\Support;

use ZipArchive;

trait CreatesUnifiedUpdatePackage
{
    /** @param array<string,string> $overrides */
    protected function unifiedUpdatePackage(array $overrides = [], string $version = '1.1.0', string $sha = ''): string
    {
        $sha = $sha === '' ? str_repeat('a', 40) : $sha;
        $entries = array_merge([
            'payload/web/server.js' => 'console.log("candidate")',
            'payload/web/RELEASE_SHA.txt' => $sha."\n",
            'payload/backend/artisan' => '#!/usr/bin/env php',
            'payload/backend/public/index.php' => '<?php',
        ], $overrides);
        $manifest = [
            'package_format_version' => 1, 'product' => 'MODRIK', 'version' => $version,
            'release_sha' => $sha, 'minimum_compatible_version' => '1.0.0',
            'runtime' => ['php' => '8.4', 'node' => '22.23.2', 'database' => 'mariadb-10.11+'],
            'payloads' => ['web' => 'payload/web', 'backend' => 'payload/backend'],
            'checksums_file' => 'checksums.json',
        ];
        $entries['manifest.json'] = json_encode($manifest, JSON_THROW_ON_ERROR);
        $checksums = [];
        foreach ($entries as $name => $contents) {
            if ($name === 'manifest.json' || str_starts_with($name, 'payload/')) {
                $checksums[$name] = hash('sha256', $contents);
            }
        }
        $entries['checksums.json'] = json_encode($checksums, JSON_THROW_ON_ERROR);

        $path = tempnam(sys_get_temp_dir(), 'modrik-update-').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }
}

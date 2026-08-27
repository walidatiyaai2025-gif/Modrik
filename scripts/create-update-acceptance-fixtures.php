<?php

declare(strict_types=1);

$output = $argv[1] ?? null;
if (! is_string($output) || $output === '') {
    fwrite(STDERR, "Usage: php scripts/create-update-acceptance-fixtures.php OUTPUT_DIRECTORY\n");
    exit(2);
}
if (! is_dir($output) && ! mkdir($output, 0700, true) && ! is_dir($output)) {
    throw new RuntimeException('Unable to create fixture output directory.');
}

$sha = str_repeat('c', 40);
$entries = [
    'payload/web/server.js' => 'console.log("MODRIK browser acceptance candidate")',
    'payload/web/RELEASE_SHA.txt' => $sha."\n",
    'payload/backend/artisan' => '#!/usr/bin/env php',
    'payload/backend/public/index.php' => '<?php',
];
$entries['manifest.json'] = json_encode([
    'package_format_version' => 1,
    'product' => 'MODRIK',
    'version' => '0.2.0',
    'release_sha' => $sha,
    'minimum_compatible_version' => '0.1.0',
    'runtime' => ['php' => '8.4', 'node' => '22.23.2', 'database' => 'mariadb-10.11+'],
    'payloads' => ['web' => 'payload/web', 'backend' => 'payload/backend'],
    'checksums_file' => 'checksums.json',
], JSON_THROW_ON_ERROR);
$checksums = [];
foreach ($entries as $name => $contents) {
    $checksums[$name] = hash('sha256', $contents);
}
$entries['checksums.json'] = json_encode($checksums, JSON_THROW_ON_ERROR);

$write = static function (string $path, array $files): void {
    $zip = new ZipArchive;
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create update acceptance fixture.');
    }
    foreach ($files as $name => $contents) {
        $zip->addFromString($name, $contents);
    }
    $zip->close();
};

$write($output.'/valid-update.zip', $entries);
$entries['payload/backend/artisan'] = 'tampered after checksum binding';
$write($output.'/corrupt-update.zip', $entries);
fwrite(STDOUT, "MODRIK_UPDATE_ACCEPTANCE_FIXTURES_OK\n");

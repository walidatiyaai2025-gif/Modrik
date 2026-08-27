<?php

namespace App\Services\Updates;

use RuntimeException;
use ZipArchive;

final class UnifiedPackageValidator
{
    private const ALLOWED_ROOTS = ['manifest.json', 'checksums.json', 'payload/', 'installer/', 'metadata/'];
    private const MAX_ENTRIES = 50000;
    private const MAX_UNCOMPRESSED_BYTES = 1073741824;

    public function validate(string $archive, ?string $currentVersion = null): PackageValidationResult
    {
        $errors = [];
        $zip = new ZipArchive;
        if (! is_file($archive) || $zip->open($archive, ZipArchive::RDONLY) !== true) {
            return new PackageValidationResult(false, [['code' => 'malformed_zip', 'message' => 'The uploaded file is not a readable ZIP archive.']]);
        }

        try {
            if ($zip->numFiles > self::MAX_ENTRIES) {
                $errors[] = ['code' => 'archive_too_large', 'message' => 'The archive contains too many entries.'];
            }

            $names = [];
            $total = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if (! is_array($stat) || ! isset($stat['name'])) {
                    $errors[] = ['code' => 'invalid_entry', 'message' => 'The archive contains an unreadable entry.'];
                    continue;
                }
                $name = str_replace('\\', '/', (string) $stat['name']);
                $names[$name] = true;
                $total += (int) ($stat['size'] ?? 0);

                if ($this->unsafePath($name)) {
                    $errors[] = ['code' => 'unsafe_path', 'message' => 'Unsafe archive path rejected.', 'path' => $name];
                } elseif (! $this->allowedRoot($name)) {
                    $errors[] = ['code' => 'unexpected_path', 'message' => 'Unexpected top-level archive path.', 'path' => $name];
                }
                if ($this->isSecretPath($name)) {
                    $errors[] = ['code' => 'embedded_secret', 'message' => 'Environment or secret files are forbidden.', 'path' => $name];
                }
                if ($this->isSymlink($stat)) {
                    $errors[] = ['code' => 'symlink_entry', 'message' => 'Symbolic links are forbidden.', 'path' => $name];
                }
            }
            if ($total > self::MAX_UNCOMPRESSED_BYTES) {
                $errors[] = ['code' => 'archive_too_large', 'message' => 'The expanded archive exceeds the safety limit.'];
            }

            $manifest = $this->jsonEntry($zip, 'manifest.json', $errors, 'missing_manifest');
            $checksums = $this->jsonEntry($zip, 'checksums.json', $errors, 'missing_checksums');
            if (is_array($manifest)) {
                $this->validateManifest($manifest, $currentVersion, $errors);
            }
            if (is_array($checksums)) {
                $this->validateChecksums($zip, $checksums, $names, $errors);
            }

            return new PackageValidationResult($errors === [], $errors, is_array($manifest) ? $manifest : null);
        } finally {
            $zip->close();
        }
    }

    /** @param list<array{code:string,message:string,path?:string}> $errors */
    private function jsonEntry(ZipArchive $zip, string $name, array &$errors, string $missingCode): ?array
    {
        $raw = $zip->getFromName($name);
        if (! is_string($raw)) {
            $errors[] = ['code' => $missingCode, 'message' => "$name is required."];
            return null;
        }
        try {
            $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : throw new RuntimeException;
        } catch (\Throwable) {
            $errors[] = ['code' => 'invalid_json', 'message' => "$name is not valid JSON."];
            return null;
        }
    }

    /** @param list<array{code:string,message:string,path?:string}> $errors */
    private function validateManifest(array $manifest, ?string $currentVersion, array &$errors): void
    {
        if (($manifest['package_format_version'] ?? null) !== 1 || ($manifest['product'] ?? null) !== 'MODRIK') {
            $errors[] = ['code' => 'unsupported_format', 'message' => 'Unsupported product or package format.'];
        }
        if (preg_match('/^[0-9a-f]{40}$/i', (string) ($manifest['release_sha'] ?? '')) !== 1) {
            $errors[] = ['code' => 'invalid_release_sha', 'message' => 'release_sha must be a full Git SHA.'];
        }
        foreach (['version', 'minimum_compatible_version'] as $key) {
            if (! is_string($manifest[$key] ?? null) || preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $manifest[$key]) !== 1) {
                $errors[] = ['code' => 'invalid_version', 'message' => "$key must be a semantic version."];
            }
        }
        $runtime = $manifest['runtime'] ?? [];
        if (! is_array($runtime) || ! str_starts_with((string) ($runtime['php'] ?? ''), '8.4') || ! str_starts_with((string) ($runtime['node'] ?? ''), '22') || ($runtime['database'] ?? null) !== 'mariadb-10.11+') {
            $errors[] = ['code' => 'incompatible_runtime', 'message' => 'Package runtime requirements are not supported by this MODRIK release line.'];
        }
        if ($currentVersion !== null && isset($manifest['minimum_compatible_version']) && version_compare($currentVersion, (string) $manifest['minimum_compatible_version'], '<')) {
            $errors[] = ['code' => 'incompatible_upgrade', 'message' => 'The installed version is older than this package supports.'];
        }
    }

    /** @param list<array{code:string,message:string,path?:string}> $errors */
    private function validateChecksums(ZipArchive $zip, array $checksums, array $names, array &$errors): void
    {
        foreach ($checksums as $path => $expected) {
            if (! is_string($path) || $this->unsafePath($path) || ! isset($names[$path]) || ! is_string($expected) || preg_match('/^[0-9a-f]{64}$/i', $expected) !== 1) {
                $errors[] = ['code' => 'invalid_checksum_entry', 'message' => 'A checksum declaration is invalid.', 'path' => (string) $path];
                continue;
            }
            $contents = $zip->getFromName($path);
            if (! is_string($contents) || ! hash_equals(strtolower($expected), hash('sha256', $contents))) {
                $errors[] = ['code' => 'checksum_mismatch', 'message' => 'Payload checksum verification failed.', 'path' => $path];
            }
        }
        foreach (array_keys($names) as $path) {
            if (str_starts_with($path, 'payload/') && ! str_ends_with($path, '/') && ! array_key_exists($path, $checksums)) {
                $errors[] = ['code' => 'undeclared_payload', 'message' => 'Every payload file must be checksum-bound.', 'path' => $path];
            }
        }
    }

    private function unsafePath(string $path): bool
    {
        return $path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1 || in_array('..', explode('/', $path), true);
    }

    private function allowedRoot(string $path): bool
    {
        foreach (self::ALLOWED_ROOTS as $root) {
            if ($path === $root || (str_ends_with($root, '/') && str_starts_with($path, $root))) return true;
        }
        return false;
    }

    private function isSecretPath(string $path): bool
    {
        $base = strtolower(basename(rtrim($path, '/')));
        return $base === '.env' || str_starts_with($base, '.env.') || in_array($base, ['id_rsa', 'id_ed25519', 'credentials.json', 'service-account.json'], true);
    }

    private function isSymlink(array $stat): bool
    {
        return (((int) ($stat['external_attributes'] ?? 0) >> 16) & 0170000) === 0120000;
    }
}

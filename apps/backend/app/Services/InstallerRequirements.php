<?php

namespace App\Services;

final class InstallerRequirements
{
    /** @return array<string,bool> */
    public function current(): array
    {
        return self::evaluate(
            PHP_VERSION,
            get_loaded_extensions(),
            [
                'storage_writable' => is_writable(storage_path()),
                'cache_writable' => is_writable(base_path('bootstrap/cache')),
                'environment_writable' => is_writable(dirname((string) config('installer.env_path', base_path('.env')))),
            ],
        );
    }

    /**
     * @param  list<string>  $extensions
     * @param  array<string,bool>  $writable
     * @return array<string,bool>
     */
    public static function evaluate(string $phpVersion, array $extensions, array $writable): array
    {
        $loaded = array_fill_keys(array_map('strtolower', $extensions), true);

        return [
            'php_8_4' => version_compare($phpVersion, '8.4.0', '>='),
            'pdo_mysql' => isset($loaded['pdo_mysql']),
            'mbstring' => isset($loaded['mbstring']),
            'openssl' => isset($loaded['openssl']),
            'zip' => isset($loaded['zip']),
            'storage_writable' => $writable['storage_writable'] ?? false,
            'cache_writable' => $writable['cache_writable'] ?? false,
            'environment_writable' => $writable['environment_writable'] ?? false,
        ];
    }

    /** @param array<string,bool> $requirements */
    public function passes(array $requirements): bool
    {
        return ! in_array(false, $requirements, true);
    }
}

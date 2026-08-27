<?php

namespace App\Services\Updates;

use RuntimeException;
use Symfony\Component\Process\Process;

final class UpdatePhpBinaryResolver
{
    public function resolve(): string
    {
        $minimum = (string) config('updates.minimum_php_version', '8.4.1');
        $configured = trim((string) config('updates.php_binary', ''));

        if ($configured !== '') {
            if ($this->isCompatible($configured, $minimum)) {
                return $configured;
            }

            throw new RuntimeException('update_php_binary_incompatible');
        }

        $candidates = array_values(array_unique([
            '/opt/cpanel/ea-php84/root/usr/bin/php',
            '/opt/alt/php84/usr/bin/php',
            '/usr/local/bin/php84',
            '/usr/bin/php8.4',
            '/usr/bin/php84',
            PHP_BINARY,
        ]));

        foreach ($candidates as $candidate) {
            if ($this->isCompatible($candidate, $minimum)) {
                return $candidate;
            }
        }

        throw new RuntimeException('update_php_binary_unavailable');
    }

    private function isCompatible(string $binary, string $minimum): bool
    {
        if (! is_file($binary) || ! is_executable($binary)) {
            return false;
        }

        try {
            $process = new Process([$binary, '-r', 'fwrite(STDOUT, PHP_VERSION);'], timeout: 15);
            $process->run();
        } catch (\Throwable) {
            return false;
        }

        if (! $process->isSuccessful()) {
            return false;
        }

        $version = trim($process->getOutput());

        return $version !== '' && version_compare($version, $minimum, '>=');
    }
}

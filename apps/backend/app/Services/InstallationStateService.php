<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

final class InstallationStateService
{
    public function lockPath(): string
    {
        return (string) config('installer.lock_path', storage_path('app/private/installation.lock'));
    }

    public function installed(): bool
    {
        return is_file($this->lockPath());
    }

    /** @return array{installed:bool} */
    public function publicState(): array
    {
        return ['installed' => $this->installed()];
    }

    public function lock(string $releaseSha): void
    {
        if (preg_match('/^[0-9a-f]{40}$/i', $releaseSha) !== 1) {
            throw new RuntimeException('Invalid release identity.');
        }
        File::ensureDirectoryExists(dirname($this->lockPath()), 0700);
        $temporary = $this->lockPath().'.'.bin2hex(random_bytes(8));
        File::put($temporary, json_encode(['release_sha' => strtolower($releaseSha), 'installed_at' => now()->toIso8601String()], JSON_THROW_ON_ERROR), true);
        if (! @rename($temporary, $this->lockPath())) {
            @unlink($temporary);
            throw new RuntimeException('Unable to create installation lock.');
        }
    }

    public function issueCompletionToken(): string
    {
        if (! $this->installed()) {
            throw new RuntimeException('Installation must be complete before issuing a finish token.');
        }
        $token = bin2hex(random_bytes(32));
        $path = $this->lockPath().'.finish';
        $temporary = $path.'.'.bin2hex(random_bytes(8));
        File::put($temporary, hash('sha256', $token), true);
        if (! @rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to create installation finish handoff.');
        }

        return $token;
    }

    public function consumeCompletionToken(?string $token): bool
    {
        $path = $this->lockPath().'.finish';
        if (preg_match('/^[0-9a-f]{64}$/', (string) $token) !== 1 || ! is_readable($path)) {
            return false;
        }
        $expected = trim((string) file_get_contents($path));
        if (preg_match('/^[0-9a-f]{64}$/', $expected) !== 1 || ! hash_equals($expected, hash('sha256', (string) $token))) {
            return false;
        }

        return @unlink($path);
    }
}

<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Throwable;

final class GovernedActivationHealthChecker implements ActivationHealthChecker
{
    public function __construct(private UpdatePhpBinaryResolver $phpBinary) {}

    public function healthy(string $releasePath, string $expectedReleaseSha): bool
    {
        $backend = $releasePath.DIRECTORY_SEPARATOR.'payload'.DIRECTORY_SEPARATOR.'backend';
        $web = $releasePath.DIRECTORY_SEPARATOR.'payload'.DIRECTORY_SEPARATOR.'web';
        $recordedSha = is_readable($web.DIRECTORY_SEPARATOR.'RELEASE_SHA.txt')
            ? strtolower(trim((string) file_get_contents($web.DIRECTORY_SEPARATOR.'RELEASE_SHA.txt')))
            : '';

        if (
            ! is_file($backend.DIRECTORY_SEPARATOR.'artisan')
            || ! is_file($backend.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php')
            || ! is_file($web.DIRECTORY_SEPARATOR.'server.js')
            || ! hash_equals(strtolower($expectedReleaseSha), $recordedSha)
            || config('app.debug') !== false
        ) {
            return false;
        }

        try {
            DB::connection()->getPdo();
            $migrationState = new Process([$this->phpBinary->resolve(), 'artisan', 'migrate:status', '--no-ansi'], $backend, timeout: 120);
            $migrationState->run();

            return $migrationState->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }
}

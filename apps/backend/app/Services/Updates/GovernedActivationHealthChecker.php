<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\DB;
use Throwable;

final class GovernedActivationHealthChecker implements ActivationHealthChecker
{
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

            $migrationFiles = glob($backend.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'*.php') ?: [];
            $expectedMigrations = array_map(
                static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
                $migrationFiles,
            );
            $appliedMigrations = array_values(array_filter(
                DB::table('migrations')->pluck('migration')->all(),
                static fn (mixed $migration): bool => is_string($migration) && $migration !== '',
            ));

            return array_diff($expectedMigrations, $appliedMigrations) === [];
        } catch (Throwable) {
            return false;
        }
    }
}

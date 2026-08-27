<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

final class CommandBackendReleaseOperator implements BackendReleaseOperator
{
    public function __construct(private UpdatePhpBinaryResolver $phpBinary) {}

    public function prepareSharedState(string $releasePath, string $sharedPath): void
    {
        $backend = $releasePath.DIRECTORY_SEPARATOR.'payload'.DIRECTORY_SEPARATOR.'backend';
        if (! is_file($backend.DIRECTORY_SEPARATOR.'artisan')) {
            throw new RuntimeException('candidate_backend_missing');
        }

        File::ensureDirectoryExists($sharedPath, 0700);

        $sourceEnv = (string) config('updates.shared_env_path', base_path('.env'));
        if (! is_file($sourceEnv) || ! is_readable($sourceEnv)) {
            throw new RuntimeException('shared_environment_missing');
        }
        $sourceStorage = (string) config('updates.shared_storage_path', storage_path());
        $sourceUploads = (string) config('updates.shared_uploads_path', storage_path('app/public'));
        if (! is_dir($sourceStorage) || ! is_writable($sourceStorage) || ! is_dir($sourceUploads) || ! is_writable($sourceUploads)) {
            throw new RuntimeException('shared_state_unavailable');
        }

        $this->replaceWithLink($backend.DIRECTORY_SEPARATOR.'.env', $sourceEnv);
        $this->replaceWithLink($backend.DIRECTORY_SEPARATOR.'storage', $sourceStorage);
        $this->replaceWithLink($backend.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'storage', $sourceStorage.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public');
        $this->replaceWithLink($backend.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'uploads', $sourceUploads);
        File::put($sharedPath.DIRECTORY_SEPARATOR.'state.json', json_encode([
            'environment_preserved' => true, 'storage_preserved' => true, 'uploads_preserved' => true,
        ], JSON_THROW_ON_ERROR), true);
    }

    public function migrate(string $releasePath): bool
    {
        return $this->artisan($releasePath, ['migrate', '--force']);
    }

    public function rebuildCaches(string $releasePath): bool
    {
        return $this->artisan($releasePath, ['optimize:clear'])
            && $this->artisan($releasePath, ['config:cache'])
            && $this->artisan($releasePath, ['route:cache'])
            && $this->artisan($releasePath, ['view:cache']);
    }

    /** @param list<string> $arguments */
    private function artisan(string $releasePath, array $arguments): bool
    {
        $backend = $releasePath.DIRECTORY_SEPARATOR.'payload'.DIRECTORY_SEPARATOR.'backend';
        $process = new Process([$this->phpBinary->resolve(), 'artisan', ...$arguments], $backend, timeout: 300);
        $process->run();

        return $process->isSuccessful();
    }

    private function replaceWithLink(string $link, string $target): void
    {
        if (is_link($link) || is_file($link)) {
            @unlink($link);
        } elseif (is_dir($link)) {
            File::deleteDirectory($link);
        }
        File::ensureDirectoryExists(dirname($link), 0700);
        if (! @symlink($target, $link)) {
            throw new RuntimeException('shared_state_link_failed');
        }
    }
}

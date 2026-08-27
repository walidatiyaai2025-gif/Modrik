<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

final class TransactionalReleaseManager
{
    public function __construct(private UnifiedPackageValidator $validator, private WebRestartAdapter $restart) {}

    /** @return array{status:string,release_id:string,previous_release:?string} */
    public function install(string $archive, string $root, ?string $currentVersion = null): array
    {
        File::ensureDirectoryExists($root);
        $lock = fopen($root.DIRECTORY_SEPARATOR.'.update.lock', 'c+');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('concurrent_update');
        }
        try {
            $validation = $this->validator->validate($archive, $currentVersion);
            if (! $validation->valid || ! is_array($validation->manifest)) {
                throw new RuntimeException('package_validation_failed');
            }
            $releaseId = $validation->manifest['version'].'-'.$validation->manifest['release_sha'];
            $staging = $root.DIRECTORY_SEPARATOR.'staging'.DIRECTORY_SEPARATOR.bin2hex(random_bytes(16));
            File::ensureDirectoryExists($staging, 0700);
            $zip = new ZipArchive;
            if ($zip->open($archive, ZipArchive::RDONLY) !== true || ! $zip->extractTo($staging)) {
                throw new RuntimeException('stage_failed');
            } $zip->close();
            $candidate = $root.DIRECTORY_SEPARATOR.'releases'.DIRECTORY_SEPARATOR.$releaseId;
            File::ensureDirectoryExists(dirname($candidate), 0700);
            if (! @rename($staging, $candidate)) {
                throw new RuntimeException('candidate_move_failed');
            }
            $current = $root.DIRECTORY_SEPARATOR.'current';
            $previous = is_dir($current) ? $root.DIRECTORY_SEPARATOR.'releases'.DIRECTORY_SEPARATOR.'.previous-'.bin2hex(random_bytes(6)) : null;
            if ($previous !== null && ! @rename($current, $previous)) {
                throw new RuntimeException('current_backup_failed');
            }
            if (! @rename($candidate, $current)) {
                if ($previous !== null) {
                    @rename($previous, $current);
                } throw new RuntimeException('activation_failed');
            }
            $restart = $this->restart->restart($current);
            if (! $restart->successful()) {
                $failed = $root.DIRECTORY_SEPARATOR.'releases'.DIRECTORY_SEPARATOR.$releaseId.'-failed';
                @rename($current, $failed);
                if ($previous !== null) {
                    @rename($previous, $current);
                }

                return ['status' => $restart->status, 'release_id' => $releaseId, 'previous_release' => $previous];
            }

            return ['status' => 'activated', 'release_id' => $releaseId, 'previous_release' => $previous];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}

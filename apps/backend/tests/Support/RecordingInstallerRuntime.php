<?php

namespace Tests\Support;

use App\Services\InstallerRuntime;
use RuntimeException;

final class RecordingInstallerRuntime implements InstallerRuntime
{
    public bool $ran = false;

    public function __construct(private bool $fail = false) {}

    public function testDatabase(array $input): void {}

    public function migrateAndCreateAdmin(array $input): void
    {
        if ($this->fail) {
            throw new RuntimeException('database_authentication_failed');
        }
        $this->ran = $input['admin_email'] === 'admin@example.test';
    }
}

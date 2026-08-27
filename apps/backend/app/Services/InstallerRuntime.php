<?php

namespace App\Services;

interface InstallerRuntime
{
    /** @param array<string,mixed> $input */
    public function testDatabase(array $input): void;

    /** @param array<string,mixed> $input */
    public function migrateAndCreateAdmin(array $input): void;
}

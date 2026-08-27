<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class LaravelInstallerRuntime implements InstallerRuntime
{
    public function testDatabase(array $input): void
    {
        $this->applyDatabaseConfig($input);
        DB::connection()->getPdo();
    }

    public function migrateAndCreateAdmin(array $input): void
    {
        $this->applyDatabaseConfig($input);
        DB::connection()->getPdo();
        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            throw new RuntimeException('Database migration failed.');
        }
        DB::transaction(function () use ($input): void {
            if (User::query()->where('role', 'admin')->exists()) {
                throw new RuntimeException('An Admin account already exists.');
            }
            User::query()->create([
                'name' => 'MODRIK Admin', 'email' => $input['admin_email'],
                'email_normalized' => mb_strtolower((string) $input['admin_email']), 'email_verified_at' => now(),
                'locale' => 'en', 'role' => 'admin', 'account_status' => 'active',
                'password' => Hash::make((string) $input['admin_password']), 'password_enabled' => true,
            ]);
        });
        Artisan::call('config:clear');
    }

    /** @param array<string,mixed> $input */
    private function applyDatabaseConfig(array $input): void
    {
        config([
            'database.default' => 'mysql', 'database.connections.mysql.host' => $input['db_host'],
            'database.connections.mysql.port' => $input['db_port'], 'database.connections.mysql.database' => $input['db_database'],
            'database.connections.mysql.username' => $input['db_username'], 'database.connections.mysql.password' => $input['db_password'],
        ]);
        DB::purge('mysql');
    }
}

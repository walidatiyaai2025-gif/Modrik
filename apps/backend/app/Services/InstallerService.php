<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class InstallerService
{
    /** @param array{db_host:string,db_port:int,db_database:string,db_username:string,db_password:string,app_url:string,web_url:string,admin_email:string,admin_password:string,release_sha:string} $input */
    public function install(array $input): void
    {
        $envPath = base_path('.env');
        if (is_file($envPath)) throw new RuntimeException('Existing Backend .env requires explicit operator recovery; installer will not overwrite it.');
        $env = $this->environment($input);
        $temporary = $envPath.'.install-'.bin2hex(random_bytes(8));
        File::put($temporary, $env, true);
        if (! @rename($temporary, $envPath)) { @unlink($temporary); throw new RuntimeException('Unable to persist Backend configuration.'); }
        try {
            $this->applyDatabaseConfig($input);
            DB::connection()->getPdo();
            if (Artisan::call('migrate', ['--force' => true]) !== 0) throw new RuntimeException('Database migration failed.');
            DB::transaction(function () use ($input): void {
                if (User::query()->where('role', 'admin')->exists()) throw new RuntimeException('An Admin account already exists.');
                User::query()->create(['name' => 'MODRIK Admin', 'email' => $input['admin_email'], 'email_normalized' => mb_strtolower($input['admin_email']), 'email_verified_at' => now(), 'locale' => 'en', 'role' => 'admin', 'account_status' => 'active', 'password' => Hash::make($input['admin_password']), 'password_enabled' => true]);
            });
            File::ensureDirectoryExists(storage_path('app/private'), 0700);
            Artisan::call('config:clear');
            app(InstallationStateService::class)->lock($input['release_sha']);
        } catch (\Throwable $exception) {
            @unlink($envPath);
            throw $exception;
        }
    }

    /** @param array<string,mixed> $input */
    public function testDatabase(array $input): void { $this->applyDatabaseConfig($input); DB::purge(); DB::connection()->getPdo(); }

    /** @param array<string,mixed> $input */
    private function applyDatabaseConfig(array $input): void
    {
        config(['database.default' => 'mysql', 'database.connections.mysql.host' => $input['db_host'], 'database.connections.mysql.port' => $input['db_port'], 'database.connections.mysql.database' => $input['db_database'], 'database.connections.mysql.username' => $input['db_username'], 'database.connections.mysql.password' => $input['db_password']]); DB::purge('mysql');
    }

    /** @param array<string,mixed> $input */
    private function environment(array $input): string
    {
        $values = ['APP_NAME' => 'MODRIK', 'APP_ENV' => 'production', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_DEBUG' => 'false', 'APP_URL' => $input['app_url'], 'MODRIK_WEB_URL' => $input['web_url'], 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $input['db_host'], 'DB_PORT' => (string) $input['db_port'], 'DB_DATABASE' => $input['db_database'], 'DB_USERNAME' => $input['db_username'], 'DB_PASSWORD' => $input['db_password']];
        return implode("\n", array_map(fn ($key, $value) => $key.'='.$this->quote((string) $value), array_keys($values), $values))."\n";
    }
    private function quote(string $value): string { return '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '', ''], $value).'"'; }
}

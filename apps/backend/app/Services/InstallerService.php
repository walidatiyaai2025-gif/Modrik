<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

final class InstallerService
{
    public function __construct(private InstallerRuntime $runtime, private InstallationStateService $state) {}

    /** @param array{db_host:string,db_port:int,db_database:string,db_username:string,db_password:string,app_url:string,web_url:string,admin_email:string,admin_password:string,release_sha:string} $input */
    public function install(array $input): void
    {
        $envPath = (string) config('installer.env_path', base_path('.env'));
        if (is_file($envPath)) {
            throw new RuntimeException('Existing Backend .env requires explicit operator recovery; installer will not overwrite it.');
        }
        $env = $this->environment($input);
        $temporary = $envPath.'.install-'.bin2hex(random_bytes(8));
        File::put($temporary, $env, true);
        if (! @rename($temporary, $envPath)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to persist Backend configuration.');
        }
        try {
            $this->runtime->migrateAndCreateAdmin($input);
            File::ensureDirectoryExists(storage_path('app/private'), 0700);
            $this->state->lock($input['release_sha']);
        } catch (\Throwable $exception) {
            @unlink($envPath);
            throw $exception;
        }
    }

    /** @param array<string,mixed> $input */
    public function testDatabase(array $input): void
    {
        $this->runtime->testDatabase($input);
    }

    /** @param array<string,mixed> $input */
    private function environment(array $input): string
    {
        $values = ['APP_NAME' => 'MODRIK', 'APP_ENV' => 'production', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_DEBUG' => 'false', 'APP_URL' => $input['app_url'], 'MODRIK_WEB_URL' => $input['web_url'], 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $input['db_host'], 'DB_PORT' => (string) $input['db_port'], 'DB_DATABASE' => $input['db_database'], 'DB_USERNAME' => $input['db_username'], 'DB_PASSWORD' => $input['db_password']];

        return implode("\n", array_map(fn ($key, $value) => $key.'='.$this->quote((string) $value), array_keys($values), $values))."\n";
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '', ''], $value).'"';
    }
}

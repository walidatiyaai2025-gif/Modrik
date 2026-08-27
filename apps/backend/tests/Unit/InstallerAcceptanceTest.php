<?php

namespace Tests\Unit;

use App\Services\InstallationStateService;
use App\Services\InstallerRequirements;
use App\Services\InstallerService;
use RuntimeException;
use Tests\Support\RecordingInstallerRuntime;
use Tests\TestCase;

final class InstallerAcceptanceTest extends TestCase
{
    public function test_clean_install_generates_environment_runs_runtime_and_locks_installer(): void
    {
        $directory = sys_get_temp_dir().'/modrik-install-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);
        config(['installer.env_path' => $directory.'/.env', 'installer.lock_path' => $directory.'/installation.lock']);
        $runtime = new RecordingInstallerRuntime;
        $service = new InstallerService($runtime, app(InstallationStateService::class));
        $service->install($this->input());

        $environment = (string) file_get_contents($directory.'/.env');
        $this->assertTrue($runtime->ran);
        $this->assertStringContainsString('APP_KEY="base64:', $environment);
        $this->assertStringContainsString('APP_DEBUG="false"', $environment);
        $this->assertFileExists($directory.'/installation.lock');
        $this->assertTrue(app(InstallationStateService::class)->installed());
        $this->deleteFixture($directory);
    }

    public function test_database_failure_removes_new_environment_and_never_leaks_secret_in_exception(): void
    {
        $directory = sys_get_temp_dir().'/modrik-install-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);
        config(['installer.env_path' => $directory.'/.env', 'installer.lock_path' => $directory.'/installation.lock']);
        $runtime = new RecordingInstallerRuntime(fail: true);

        try {
            (new InstallerService($runtime, app(InstallationStateService::class)))->install($this->input());
            $this->fail('Installation should fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('DatabaseSecret1', $exception->getMessage());
            $this->assertFileDoesNotExist($directory.'/.env');
            $this->assertFileDoesNotExist($directory.'/installation.lock');
        } finally {
            $this->deleteFixture($directory);
        }
    }

    public function test_unsupported_php_missing_extension_and_unwritable_path_fail_requirements(): void
    {
        $base = ['pdo_mysql', 'mbstring', 'openssl', 'zip'];
        $writable = ['storage_writable' => true, 'cache_writable' => true, 'environment_writable' => true];
        $this->assertFalse(InstallerRequirements::evaluate('8.3.9', $base, $writable)['php_8_4']);
        $this->assertFalse(InstallerRequirements::evaluate('8.4.0', array_values(array_diff($base, ['zip'])), $writable)['zip']);
        $this->assertFalse(InstallerRequirements::evaluate('8.4.0', $base, array_merge($writable, ['storage_writable' => false]))['storage_writable']);
    }

    /** @return array{db_host:string,db_port:int,db_database:string,db_username:string,db_password:string,app_url:string,web_url:string,admin_email:string,admin_password:string,release_sha:string} */
    private function input(): array
    {
        return [
            'db_host' => '127.0.0.1', 'db_port' => 3306, 'db_database' => 'modrik',
            'db_username' => 'modrik', 'db_password' => 'DatabaseSecret1',
            'app_url' => 'https://api.example.test', 'web_url' => 'https://example.test',
            'admin_email' => 'admin@example.test', 'admin_password' => 'AdminPassword1',
            'release_sha' => str_repeat('b', 40),
        ];
    }

    private function deleteFixture(string $directory): void
    {
        foreach (glob($directory.'/*', GLOB_NOSORT) ?: [] as $path) {
            @unlink($path);
        }
        @unlink($directory.'/.env');
        @rmdir($directory);
    }
}

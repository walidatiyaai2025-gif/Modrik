<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

final class DemoAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_admin_is_seeded_from_external_demo_config_without_hardcoded_credentials(): void
    {
        config()->set('modrik.demo.admin.email', 'admin.demo@example.test');
        config()->set('modrik.demo.admin.password', 'Demo-Only-Password-123!');

        $this->seed(DemoAdminSeeder::class);

        $admin = User::query()->where('email_normalized', 'admin.demo@example.test')->firstOrFail();

        $this->assertSame('admin', $admin->role);
        $this->assertSame('active', $admin->account_status);
        $this->assertTrue($admin->password_enabled);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('Demo-Only-Password-123!', $admin->password));
    }

    public function test_demo_admin_seeder_is_a_no_op_without_configured_credentials(): void
    {
        config()->set('modrik.demo.admin.email', '');
        config()->set('modrik.demo.admin.password', '');

        $this->seed(DemoAdminSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_demo_admin_credentials_fail_closed_when_incomplete_or_invalid(): void
    {
        config()->set('modrik.demo.admin.email', 'admin.demo@example.test');
        config()->set('modrik.demo.admin.password', '');

        try {
            $this->seed(DemoAdminSeeder::class);
            $this->fail('Incomplete demo credentials should have been rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('required together', $exception->getMessage());
        }

        config()->set('modrik.demo.admin.email', 'not-an-email');
        config()->set('modrik.demo.admin.password', 'Demo-Only-Password-123!');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('valid email address');

        $this->seed(DemoAdminSeeder::class);
    }
}

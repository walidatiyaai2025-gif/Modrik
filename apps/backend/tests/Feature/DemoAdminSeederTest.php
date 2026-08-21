<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class DemoAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_admin_is_seeded_from_config_without_hardcoded_credentials(): void
    {
        config()->set('modrik.fixture.admin.email', 'admin.demo@example.test');
        config()->set('modrik.fixture.admin.password', 'Demo-Only-Password-123!');

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
        config()->set('modrik.fixture.admin.email', '');
        config()->set('modrik.fixture.admin.password', '');

        $this->seed(DemoAdminSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }
}

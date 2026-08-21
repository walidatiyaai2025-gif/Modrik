<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

final class DemoAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) config('modrik.fixture.admin.email', ''));
        $password = (string) config('modrik.fixture.admin.password', '');

        if ($email === '' && $password === '') {
            return;
        }

        if ($email === '' || $password === '') {
            throw new RuntimeException('Both MODRIK_DEMO_ADMIN_EMAIL and MODRIK_DEMO_ADMIN_PASSWORD are required together.');
        }

        if (strlen($password) < 12) {
            throw new RuntimeException('MODRIK_DEMO_ADMIN_PASSWORD must contain at least 12 characters.');
        }

        $normalized = mb_strtolower($email);

        User::query()->updateOrCreate(
            ['email_normalized' => $normalized],
            [
                'name' => 'MODRIK Demo Administrator',
                'email' => $email,
                'email_normalized' => $normalized,
                'email_verified_at' => now(),
                'locale' => 'en',
                'role' => 'admin',
                'account_status' => 'active',
                'password' => $password,
                'password_enabled' => true,
                'deleted_at' => null,
            ],
        );
    }
}

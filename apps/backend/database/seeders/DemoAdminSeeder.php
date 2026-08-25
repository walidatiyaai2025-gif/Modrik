<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

final class DemoAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) config('modrik.demo.admin.email', ''));
        $password = (string) config('modrik.demo.admin.password', '');

        if ($email === '' && $password === '') {
            return;
        }

        if ($email === '' || $password === '') {
            throw new RuntimeException('Both MODRIK_DEMO_ADMIN_EMAIL and MODRIK_DEMO_ADMIN_PASSWORD are required together.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('MODRIK_DEMO_ADMIN_EMAIL must be a valid email address.');
        }

        if (mb_strlen($password) < 12 || mb_strlen($password) > 128) {
            throw new RuntimeException('MODRIK_DEMO_ADMIN_PASSWORD must contain between 12 and 128 characters.');
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

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

final class DemoStudentSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) config('modrik.demo.student.email', ''));
        $password = (string) config('modrik.demo.student.password', '');

        if ($email === '' && $password === '') {
            return;
        }

        if ($email === '' || $password === '') {
            throw new RuntimeException('Both MODRIK_DEMO_STUDENT_EMAIL and MODRIK_DEMO_STUDENT_PASSWORD are required together.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('MODRIK_DEMO_STUDENT_EMAIL must be a valid email address.');
        }

        $passwordLength = mb_strlen($password);
        if ($passwordLength < 12 || $passwordLength > 128) {
            throw new RuntimeException('MODRIK_DEMO_STUDENT_PASSWORD must contain between 12 and 128 characters.');
        }

        $normalizedEmail = mb_strtolower($email);
        $existing = User::query()->where('email_normalized', $normalizedEmail)->first();
        if ($existing instanceof User && (string) $existing->getAttribute('role') !== 'student') {
            throw new RuntimeException('MODRIK_DEMO_STUDENT_EMAIL is already assigned to a non-student account.');
        }

        User::query()->updateOrCreate(
            ['email_normalized' => $normalizedEmail],
            [
                'name' => 'MODRIK Demo Learner',
                'email' => $email,
                'email_normalized' => $normalizedEmail,
                'email_verified_at' => now(),
                'locale' => 'en',
                'role' => 'student',
                'account_status' => 'active',
                'password' => $password,
                'password_enabled' => true,
                'deleted_at' => null,
            ],
        );
    }
}

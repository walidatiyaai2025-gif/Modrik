<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

final class DemoStudentSeeder extends Seeder
{
    private const TEST_EMAIL = 'pilot.student@modrik.test';

    private const TEST_PASSWORD = 'ModrikPilotRealSession!2026';

    public function run(): void
    {
        $email = trim((string) config('modrik.demo.student.email', ''));
        $password = (string) config('modrik.demo.student.password', '');

        if ($email === '' && $password === '' && app()->environment('testing')) {
            $email = self::TEST_EMAIL;
            $password = self::TEST_PASSWORD;
        }

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

        $learner = User::query()->find(LearningSliceSeeder::USER_ID);
        if (! $learner instanceof User) {
            throw new RuntimeException('The reference learner must be seeded before DemoStudentSeeder runs.');
        }

        $normalizedEmail = mb_strtolower($email);
        $emailOwner = User::query()
            ->where('email_normalized', $normalizedEmail)
            ->whereKeyNot($learner->getKey())
            ->exists();

        if ($emailOwner) {
            throw new RuntimeException('MODRIK_DEMO_STUDENT_EMAIL is already assigned to another account.');
        }

        $learner->forceFill([
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
        ])->save();
    }
}

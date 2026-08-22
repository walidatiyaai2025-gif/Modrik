<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

final class DemoStudentSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) config('modrik.fixture.student.email', ''));
        $password = (string) config('modrik.fixture.student.password', '');

        if ($email === '' && $password === '') {
            return;
        }

        if (! (bool) config('modrik.fixture.enabled')) {
            throw new RuntimeException('Demo student credentials may only be applied while MODRIK fixture mode is enabled.');
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
            throw new RuntimeException('The fixture learner must be seeded before DemoStudentSeeder runs.');
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

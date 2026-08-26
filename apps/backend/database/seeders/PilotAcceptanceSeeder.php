<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PilotAcceptanceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('PilotAcceptanceSeeder is test/acceptance-only.');
        }

        $email = mb_strtolower(trim((string) config('modrik.demo.student.email', '')));
        if ($email === '' || ! str_starts_with($email, 'pilot.') || ! str_ends_with($email, '@modrik.test')) {
            throw new RuntimeException('PilotAcceptanceSeeder requires the ephemeral pilot acceptance student identity.');
        }

        $student = User::query()->where('email_normalized', $email)->first();
        if (! $student instanceof User || (string) $student->getAttribute('role') !== 'student') {
            throw new RuntimeException('The real-session pilot student must exist before acceptance data is seeded.');
        }

        $this->call(LearningSliceSeeder::class);

        DB::table('user_academic_contexts')
            ->where('user_id', LearningSliceSeeder::USER_ID)
            ->where('status', 'active')
            ->update([
                'user_id' => (string) $student->getKey(),
                'updated_at' => now(),
            ]);

        DB::table('academic_track_authorizations')
            ->where('user_id', LearningSliceSeeder::USER_ID)
            ->update([
                'user_id' => (string) $student->getKey(),
                'updated_at' => now(),
            ]);
    }
}

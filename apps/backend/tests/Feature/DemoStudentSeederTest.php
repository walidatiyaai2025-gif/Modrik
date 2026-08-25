<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoStudentSeeder;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

final class DemoStudentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_student_is_seeded_as_an_independent_real_account(): void
    {
        $this->seed(LearningSliceSeeder::class);
        $fixtureLearner = User::query()->findOrFail(LearningSliceSeeder::USER_ID);
        $fixtureEmail = $fixtureLearner->email_normalized;

        config()->set('modrik.demo.student.email', 'student.demo@example.test');
        config()->set('modrik.demo.student.password', 'Demo-Student-Password-123!');

        $this->seed(DemoStudentSeeder::class);

        $learner = User::query()->where('email_normalized', 'student.demo@example.test')->firstOrFail();

        $this->assertNotSame(LearningSliceSeeder::USER_ID, $learner->id);
        $this->assertSame('student.demo@example.test', $learner->email_normalized);
        $this->assertSame('student', $learner->role);
        $this->assertSame('active', $learner->account_status);
        $this->assertTrue($learner->password_enabled);
        $this->assertNotNull($learner->email_verified_at);
        $this->assertTrue(Hash::check('Demo-Student-Password-123!', $learner->password));
        $this->assertSame($fixtureEmail, User::query()->findOrFail(LearningSliceSeeder::USER_ID)->email_normalized);
        $this->assertTrue(DB::table('user_academic_contexts')->where('user_id', LearningSliceSeeder::USER_ID)->exists());
    }

    public function test_repeat_seeding_preserves_the_independent_demo_account_identity(): void
    {
        config()->set('modrik.demo.student.email', 'student.demo@example.test');
        config()->set('modrik.demo.student.password', 'Demo-Student-Password-123!');

        $this->seed(DemoStudentSeeder::class);
        $firstId = User::query()->where('email_normalized', 'student.demo@example.test')->firstOrFail()->id;
        $this->seed(DemoStudentSeeder::class);

        $this->assertSame(1, User::query()->where('email_normalized', 'student.demo@example.test')->count());
        $this->assertSame($firstId, User::query()->where('email_normalized', 'student.demo@example.test')->firstOrFail()->id);
    }

    public function test_demo_student_seeder_is_a_no_op_without_configured_credentials(): void
    {
        config()->set('modrik.demo.student.email', '');
        config()->set('modrik.demo.student.password', '');

        $this->seed(DemoStudentSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_demo_student_credentials_do_not_depend_on_fixture_mode(): void
    {
        config()->set('modrik.demo.student.email', 'student.demo@example.test');
        config()->set('modrik.demo.student.password', 'Demo-Student-Password-123!');

        $this->seed(DemoStudentSeeder::class);

        $this->assertDatabaseHas('users', [
            'email_normalized' => 'student.demo@example.test',
            'role' => 'student',
            'account_status' => 'active',
        ]);
    }

    public function test_demo_student_credentials_require_both_values(): void
    {
        config()->set('modrik.demo.student.email', 'student.demo@example.test');
        config()->set('modrik.demo.student.password', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('are required together');

        $this->seed(DemoStudentSeeder::class);
    }

    public function test_demo_student_credentials_validate_email_and_password_length(): void
    {
        config()->set('modrik.demo.student.email', 'not-an-email');
        config()->set('modrik.demo.student.password', 'Demo-Student-Password-123!');

        try {
            $this->seed(DemoStudentSeeder::class);
            $this->fail('Invalid email should have been rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('valid email address', $exception->getMessage());
        }

        config()->set('modrik.demo.student.email', 'student.demo@example.test');
        config()->set('modrik.demo.student.password', 'too-short');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('between 12 and 128 characters');

        $this->seed(DemoStudentSeeder::class);
    }
}

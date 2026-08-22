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

    public function test_demo_student_credentials_are_applied_to_existing_fixture_learner(): void
    {
        config()->set('modrik.fixture.enabled', true);
        $this->seed(LearningSliceSeeder::class);

        config()->set('modrik.fixture.student.email', 'student.demo@example.test');
        config()->set('modrik.fixture.student.password', 'Demo-Student-Password-123!');

        $this->seed(DemoStudentSeeder::class);

        $learner = User::query()->findOrFail(LearningSliceSeeder::USER_ID);

        $this->assertSame(LearningSliceSeeder::USER_ID, $learner->id);
        $this->assertSame('student.demo@example.test', $learner->email_normalized);
        $this->assertSame('student', $learner->role);
        $this->assertSame('active', $learner->account_status);
        $this->assertTrue($learner->password_enabled);
        $this->assertNotNull($learner->email_verified_at);
        $this->assertTrue(Hash::check('Demo-Student-Password-123!', $learner->password));
        $this->assertTrue(DB::table('user_academic_contexts')->where('user_id', $learner->id)->exists());
        $this->assertSame(1, User::query()->whereKey(LearningSliceSeeder::USER_ID)->count());
    }

    public function test_repeat_seeding_preserves_the_existing_fixture_learner_identity(): void
    {
        config()->set('modrik.fixture.enabled', true);
        $this->seed(LearningSliceSeeder::class);
        config()->set('modrik.fixture.student.email', 'student.demo@example.test');
        config()->set('modrik.fixture.student.password', 'Demo-Student-Password-123!');

        $this->seed(DemoStudentSeeder::class);
        $this->seed(DemoStudentSeeder::class);

        $this->assertSame(1, User::query()->whereKey(LearningSliceSeeder::USER_ID)->count());
        $this->assertTrue(DB::table('user_academic_contexts')->where('user_id', LearningSliceSeeder::USER_ID)->exists());
    }

    public function test_demo_student_seeder_is_a_no_op_without_configured_credentials(): void
    {
        config()->set('modrik.fixture.enabled', true);
        $this->seed(LearningSliceSeeder::class);
        $before = User::query()->findOrFail(LearningSliceSeeder::USER_ID)->password;

        config()->set('modrik.fixture.student.email', '');
        config()->set('modrik.fixture.student.password', '');

        $this->seed(DemoStudentSeeder::class);

        $learner = User::query()->findOrFail(LearningSliceSeeder::USER_ID);
        $this->assertSame($before, $learner->password);
    }

    public function test_demo_student_credentials_fail_closed_outside_fixture_mode(): void
    {
        config()->set('modrik.fixture.enabled', false);
        config()->set('modrik.fixture.student.email', 'student.demo@example.test');
        config()->set('modrik.fixture.student.password', 'Demo-Student-Password-123!');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fixture mode is enabled');

        $this->seed(DemoStudentSeeder::class);
    }

    public function test_demo_student_credentials_require_both_values(): void
    {
        config()->set('modrik.fixture.enabled', true);
        config()->set('modrik.fixture.student.email', 'student.demo@example.test');
        config()->set('modrik.fixture.student.password', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('are required together');

        $this->seed(DemoStudentSeeder::class);
    }

    public function test_demo_student_credentials_validate_email_and_password_length(): void
    {
        config()->set('modrik.fixture.enabled', true);
        config()->set('modrik.fixture.student.email', 'not-an-email');
        config()->set('modrik.fixture.student.password', 'Demo-Student-Password-123!');

        try {
            $this->seed(DemoStudentSeeder::class);
            $this->fail('Invalid email should have been rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('valid email address', $exception->getMessage());
        }

        config()->set('modrik.fixture.student.email', 'student.demo@example.test');
        config()->set('modrik.fixture.student.password', 'too-short');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('between 12 and 128 characters');

        $this->seed(DemoStudentSeeder::class);
    }
}

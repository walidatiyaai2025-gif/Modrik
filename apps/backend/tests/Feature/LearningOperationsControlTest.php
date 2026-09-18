<?php

namespace Tests\Feature;

use App\Exceptions\LearningOperationBlocked;
use App\Filament\Pages\LearningOperationsControl;
use App\Models\User;
use App\Services\LearningOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class LearningOperationsControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_learning_operations_is_discoverable_admin_only_and_localized(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'account_status' => 'active']);

        $this->actingAs($admin)
            ->get('/admin/learning-operations')
            ->assertOk()
            ->assertSee('Learning feature controls')
            ->assertSee('Learning jobs')
            ->assertSee('blocked dependency')
            ->assertSee('data-testid="modrik-learning-operations"', false);

        $this->actingAs($student)->get('/admin/learning-operations')->assertForbidden();

        App::setLocale('ar');
        self::assertSame('تشغيل التعلم', LearningOperationsControl::getNavigationLabel());
        App::setLocale('fr');
        self::assertSame('Opérations apprentissage', LearningOperationsControl::getNavigationLabel());
    }

    public function test_feature_controls_are_versioned_audited_and_bounded(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $service = app(LearningOperationsService::class);

        $saved = $service->updateFeature(
            'daily_plan',
            'pilot',
            ['academic_years' => ['Year 6', 'Year 7']],
            0,
            'Pilot the daily plan only for the approved school years.',
            (string) $admin->id,
        );

        self::assertSame('pilot', $saved['state']);
        self::assertSame(1, $saved['version']);
        self::assertSame(['academic_years' => ['Year 6', 'Year 7']], $saved['scope']);
        $this->assertDatabaseHas('learning_feature_control_audits', [
            'actor_id' => $admin->id,
            'to_version' => 1,
        ]);

        $definitions = $service->featureDefinitions();
        self::assertArrayNotHasKey('scoring_authority', $definitions);
        self::assertArrayNotHasKey('privacy_policy', $definitions);
        self::assertArrayNotHasKey('publication_integrity', $definitions);
    }

    public function test_livewire_feature_change_requires_reason_and_creates_audit(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $this->actingAs($admin);

        Livewire::test(LearningOperationsControl::class)
            ->set('featureStates.student_quiz', 'disabled')
            ->set('featureReasons.student_quiz', 'Emergency kill switch for a controlled learning runtime test.')
            ->call('saveFeature', 'student_quiz')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('learning_feature_controls', [
            'feature_key' => 'student_quiz',
            'state' => 'disabled',
            'version' => 1,
        ]);
        $this->assertDatabaseHas('learning_feature_control_audits', [
            'actor_id' => $admin->id,
            'to_version' => 1,
        ]);
    }

    public function test_blocked_dependency_job_never_records_fake_success(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $service = app(LearningOperationsService::class);

        try {
            $service->runNow('mastery_recalculation', (string) $admin->id);
            self::fail('Dependency-blocked job must not run.');
        } catch (LearningOperationBlocked $exception) {
            self::assertSame('LEARNING_JOB_DEPENDENCY_BLOCKED', $exception->operationCode);
        }

        $this->assertDatabaseMissing('learning_job_runs', ['status' => 'success']);
    }

    public function test_ready_jobs_record_truthful_counts_and_pause_resume_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $service = app(LearningOperationsService::class);

        $run = $service->runNow('content_integrity', (string) $admin->id);
        self::assertSame('success', $run['status']);
        self::assertNotNull($run['counts']);
        $this->assertDatabaseHas('learning_job_controls', [
            'job_key' => 'content_integrity',
            'last_status' => 'success',
        ]);

        $job = $service->jobs()['content_integrity'];
        $paused = $service->setJobPaused(
            'content_integrity',
            true,
            (int) $job['version'],
            'Pause content integrity during the controlled maintenance window.',
            (string) $admin->id,
        );
        self::assertTrue($paused['paused']);

        $resumed = $service->setJobPaused(
            'content_integrity',
            false,
            (int) $paused['version'],
            'Resume content integrity after the maintenance window completed.',
            (string) $admin->id,
        );
        self::assertFalse($resumed['paused']);
        self::assertSame(2, DB::table('learning_job_control_audits')->count());
    }

    public function test_runtime_cleanup_is_database_backed_and_bounded(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $now = now();
        DB::table('idempotency_keys')->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'actor_id' => $admin->id,
            'operation' => 'test.expired',
            'key_digest' => str_repeat('a', 64),
            'request_hash' => str_repeat('b', 64),
            'state' => 'completed',
            'response_status' => 200,
            'response_body' => '{}',
            'expires_at' => $now->copy()->subMinute(),
            'completed_at' => $now->copy()->subMinute(),
            'created_at' => $now->copy()->subHour(),
            'updated_at' => $now->copy()->subMinute(),
        ]);

        app(LearningOperationsService::class)->runNow('runtime_cleanup', (string) $admin->id);

        $this->assertDatabaseMissing('idempotency_keys', ['operation' => 'test.expired']);
        $this->assertDatabaseHas('learning_job_controls', [
            'job_key' => 'runtime_cleanup',
            'last_status' => 'success',
        ]);
    }
}

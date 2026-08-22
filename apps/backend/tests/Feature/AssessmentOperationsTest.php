<?php

namespace Tests\Feature;

use App\Filament\Pages\AssessmentOperations;
use App\Models\User;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssessmentOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_inspect_blueprint_and_immutable_attempt_snapshot_impact(): void
    {
        config()->set('modrik.fixture.enabled', true);
        $this->seed(LearningSliceSeeder::class);

        DB::table('quizzes')->where('id', LearningSliceSeeder::QUIZ_ID)->update([
            'blueprint_version' => 3,
            'blueprint' => json_encode([
                'question_order' => 'fixed',
                'slots' => [
                    ['count' => 1, 'difficulty' => 'core'],
                ],
            ], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        DB::table('attempts')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => LearningSliceSeeder::USER_ID,
            'academic_context_id' => LearningSliceSeeder::CONTEXT_ID,
            'quiz_id' => LearningSliceSeeder::QUIZ_ID,
            'status' => 'in_progress',
            'seed_encrypted' => 'fixture-read-only-seed',
            'seed_fingerprint' => hash('sha256', 'fixture-read-only-seed'),
            'blueprint_version' => 2,
            'scope_snapshot' => json_encode([
                'blueprint_version' => 2,
                'question_order_policy' => 'shuffle',
            ], JSON_THROW_ON_ERROR),
            'ordering_algorithm' => 'modrik-fy-v1',
            'started_at' => now(),
            'completed_at' => null,
            'archived_at' => null,
            'score' => null,
            'max_score' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
            'locale' => 'en',
        ]);

        $this->actingAs($admin)
            ->get('/admin/assessment-operations')
            ->assertOk()
            ->assertSee('Assessment Operations')
            ->assertSee('Assessment operational truth')
            ->assertSee('Study plan practice')
            ->assertSee('Question order policy')
            ->assertSee('fixed')
            ->assertSee('Immutable attempt history is active.')
            ->assertSee('v2')
            ->assertSee('backend_contract_missing')
            ->assertSee('Open Question Bank')
            ->assertSee('data-testid="modrik-assessment-operations"', false);
    }

    public function test_content_team_has_read_access_but_student_does_not(): void
    {
        config()->set('modrik.fixture.enabled', true);
        $this->seed(LearningSliceSeeder::class);

        $contentTeam = User::factory()->create([
            'role' => 'content_team',
            'account_status' => 'active',
            'locale' => 'en',
        ]);

        $this->actingAs($contentTeam)
            ->get('/admin/assessment-operations')
            ->assertOk()
            ->assertSee('Assessment operational truth');

        $student = User::factory()->create([
            'role' => 'student',
            'account_status' => 'active',
            'locale' => 'en',
        ]);

        $response = $this->actingAs($student)->get('/admin/assessment-operations');
        $this->assertTrue(in_array($response->getStatusCode(), [403, 404], true));
    }

    public function test_surface_has_no_ui_only_assessment_mutation_or_attempt_authority_controls(): void
    {
        $view = (string) file_get_contents(resource_path('views/filament/pages/assessment-operations.blade.php'));
        $page = (string) file_get_contents(app_path('Filament/Pages/AssessmentOperations.php'));

        $this->assertStringNotContainsString('wire:click="publish', $view);
        $this->assertStringNotContainsString('wire:click="archive', $view);
        $this->assertStringNotContainsString('wire:click="disable', $view);
        $this->assertStringNotContainsString('wire:model.live="seed', $view);
        $this->assertStringNotContainsString('seed_encrypted', $view);
        $this->assertStringNotContainsString("DB::table('quizzes')->update", $page);
        $this->assertStringNotContainsString("DB::table('questions')->update", $page);
        $this->assertStringNotContainsString("DB::table('attempts')->update", $page);
    }

    public function test_navigation_is_localized_and_matrix_splits_assessment_admin_truthfully(): void
    {
        App::setLocale('en');
        $this->assertSame('Assessment Operations', AssessmentOperations::getNavigationLabel());

        App::setLocale('ar');
        $this->assertSame('عمليات التقييم', AssessmentOperations::getNavigationLabel());

        App::setLocale('fr');
        $this->assertSame('Opérations d’évaluation', AssessmentOperations::getNavigationLabel());

        $matrix = (string) file_get_contents(base_path('../../docs/product/capability-surface-matrix.yaml'));
        $this->assertStringContainsString('id: admin.assessment.question_bank_visibility', $matrix);
        $this->assertStringContainsString('id: admin.assessment.catalogue', $matrix);
        $this->assertStringContainsString('id: admin.assessment.lifecycle_availability', $matrix);
        $this->assertStringContainsString('id: admin.assessment.blueprint_configuration', $matrix);
        $this->assertStringContainsString('id: assessment.authoritative_randomization', $matrix);
        $this->assertStringContainsString('status: backend_contract_missing', $matrix);
        $this->assertStringNotContainsString('id: admin.exam.question_management', $matrix);
    }
}

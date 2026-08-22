<?php

namespace Tests\Feature;

use App\Filament\Pages\AssessmentQuestionBank;
use App\Models\User;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class AssessmentQuestionBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_find_published_questions_answers_explanations_and_quiz_membership(): void
    {
        config()->set('modrik.fixture.enabled', true);
        $this->seed(LearningSliceSeeder::class);

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
            'locale' => 'en',
        ]);

        $this->actingAs($admin)
            ->get('/admin/assessment-question-bank')
            ->assertOk()
            ->assertSee('Question Bank & Assessments')
            ->assertSee('Which plan includes a review step?')
            ->assertSee('Study, then review')
            ->assertSee('Correct answer')
            ->assertSee('A review step helps check what was understood.')
            ->assertSee('Study plan practice')
            ->assertSee('Approved answer')
            ->assertSee('Technical traceability')
            ->assertSee('data-testid="modrik-assessment-question-bank"', false);
    }

    public function test_content_team_has_read_visibility_but_student_does_not(): void
    {
        config()->set('modrik.fixture.enabled', true);
        $this->seed(LearningSliceSeeder::class);

        $contentTeam = User::factory()->create([
            'role' => 'content_team',
            'account_status' => 'active',
            'locale' => 'en',
        ]);

        $this->actingAs($contentTeam)
            ->get('/admin/assessment-question-bank')
            ->assertOk()
            ->assertSee('Which plan includes a review step?');

        $student = User::factory()->create([
            'role' => 'student',
            'account_status' => 'active',
            'locale' => 'en',
        ]);

        $response = $this->actingAs($student)->get('/admin/assessment-question-bank');
        $this->assertTrue(in_array($response->getStatusCode(), [403, 404], true));
    }

    public function test_question_bank_filters_are_database_backed_and_attempt_authority_is_not_exposed(): void
    {
        $view = (string) file_get_contents(resource_path('views/filament/pages/assessment-question-bank.blade.php'));
        $page = (string) file_get_contents(app_path('Filament/Pages/AssessmentQuestionBank.php'));

        $this->assertStringContainsString('wire:model.live="trackId"', $view);
        $this->assertStringContainsString('wire:model.live="subjectNodeId"', $view);
        $this->assertStringContainsString('wire:model.live="quizId"', $view);
        $this->assertStringNotContainsString('wire:model="trackId"', $view);
        $this->assertStringNotContainsString('wire:model="subjectNodeId"', $view);
        $this->assertStringNotContainsString('wire:model="quizId"', $view);
        $this->assertStringNotContainsString('wire:model.live="seed"', $view);
        $this->assertStringNotContainsString('wire:model.live="questionOrder"', $view);
        $this->assertStringNotContainsString('wire:model.live="selectedQuestionSet"', $view);
        $this->assertStringNotContainsString('public string $seed', $page);
        $this->assertStringNotContainsString('public array $questionOrder', $page);
    }

    public function test_assessment_navigation_is_localized_and_governance_matrix_records_split_contract_boundaries(): void
    {
        App::setLocale('en');
        $this->assertSame('Question Bank & Assessments', AssessmentQuestionBank::getNavigationLabel());

        App::setLocale('ar');
        $this->assertSame('بنك الأسئلة والاختبارات', AssessmentQuestionBank::getNavigationLabel());

        App::setLocale('fr');
        $this->assertSame('Banque de questions', AssessmentQuestionBank::getNavigationLabel());

        $matrix = (string) file_get_contents(base_path('../../docs/product/capability-surface-matrix.yaml'));
        $this->assertStringContainsString('id: admin.assessment.question_bank_visibility', $matrix);
        $this->assertStringContainsString('surface: AssessmentQuestionBank', $matrix);
        $this->assertStringContainsString('id: admin.assessment.lifecycle_availability', $matrix);
        $this->assertStringContainsString('id: admin.assessment.blueprint_configuration', $matrix);
        $this->assertStringContainsString('status: backend_contract_missing', $matrix);
        $this->assertStringContainsString('id: assessment.authoritative_randomization', $matrix);
        $this->assertStringContainsString('classification: internal_non_editable', $matrix);
        $this->assertStringContainsString('New-attempt seed, selected set/order, resume order and immutable grading snapshots remain Backend-owned', $matrix);
        $this->assertStringNotContainsString('id: admin.exam.question_management', $matrix);
    }
}

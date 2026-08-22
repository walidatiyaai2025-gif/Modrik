<?php

namespace Tests\Feature;

use App\Filament\Pages\AssessmentQualityReview;
use App\Models\User;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssessmentQualityReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_review_persisted_quality_metadata_and_effective_option_order_safety(): void
    {
        config()->set('modrik.fixture.enabled', true);
        $this->seed(LearningSliceSeeder::class);

        $questionIds = DB::table('questions')->orderBy('id')->pluck('id')->map(static fn ($id): string => (string) $id)->values()->all();
        $this->assertGreaterThanOrEqual(2, count($questionIds));

        DB::table('questions')->where('id', $questionIds[0])->update([
            'assessment_metadata' => json_encode([
                'section' => 'reading',
                'difficulty' => 'core',
                'concepts' => ['review-step'],
            ], JSON_THROW_ON_ERROR),
            'option_shuffle_safe' => true,
            'updated_at' => now(),
        ]);
        DB::table('questions')->where('id', $questionIds[1])->update([
            'assessment_metadata' => json_encode([
                'section' => 'sequence',
                'difficulty' => 'core',
                'option_order_semantics' => 'sequence',
            ], JSON_THROW_ON_ERROR),
            'option_shuffle_safe' => true,
            'updated_at' => now(),
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
            'locale' => 'en',
        ]);

        $this->actingAs($admin)
            ->get('/admin/assessment-quality-review')
            ->assertOk()
            ->assertSee('Question Quality Review')
            ->assertSee('Question quality signals — read only')
            ->assertSee('reading')
            ->assertSee('review-step')
            ->assertSee('Shuffle-safe')
            ->assertSee('Safety override active')
            ->assertSee('Ordering semantics require preservation.')
            ->assertSee('sequence')
            ->assertSee('Historical attempt snapshots are aggregate-only here.')
            ->assertSee('Open Question Bank details')
            ->assertSee('data-testid="modrik-assessment-quality-review"', false)
            ->assertDontSee('seed_encrypted')
            ->assertDontSee('attempt_answers');
    }

    public function test_content_team_can_review_quality_but_student_cannot(): void
    {
        config()->set('modrik.fixture.enabled', true);
        $this->seed(LearningSliceSeeder::class);

        $contentTeam = User::factory()->create([
            'role' => 'content_team',
            'account_status' => 'active',
            'locale' => 'en',
        ]);

        $this->actingAs($contentTeam)
            ->get('/admin/assessment-quality-review')
            ->assertOk()
            ->assertSee('Question quality signals — read only');

        $student = User::factory()->create([
            'role' => 'student',
            'account_status' => 'active',
            'locale' => 'en',
        ]);

        $response = $this->actingAs($student)->get('/admin/assessment-quality-review');
        $this->assertTrue(in_array($response->getStatusCode(), [403, 404], true));
    }

    public function test_quality_filters_are_database_backed_and_surface_has_no_mutation_or_attempt_authority(): void
    {
        $view = (string) file_get_contents(resource_path('views/filament/pages/assessment-quality-review.blade.php'));
        $page = (string) file_get_contents(app_path('Filament/Pages/AssessmentQualityReview.php'));

        $this->assertStringContainsString('wire:model.live="statusFilter"', $view);
        $this->assertStringContainsString('wire:model.live="metadataFilter"', $view);
        $this->assertStringContainsString('wire:model.live="shuffleFilter"', $view);
        $this->assertStringContainsString('wire:model.live.debounce.250ms="search"', $view);
        $this->assertStringContainsString("'effective_shuffle_safe'", $page);
        $this->assertStringContainsString("'option_order_semantics'", $page);
        $this->assertStringContainsString("'sequence_sensitive'", $page);
        $this->assertStringContainsString("'image_letter_mapping'", $page);
        $this->assertStringContainsString("'all_none_semantics'", $page);
        $this->assertStringNotContainsString('wire:click="publish', $view);
        $this->assertStringNotContainsString('wire:click="approve', $view);
        $this->assertStringNotContainsString('wire:model.live="seed', $view);
        $this->assertStringNotContainsString('attempt_answers', $page);
        $this->assertStringNotContainsString('seed_encrypted', $page);
        $this->assertStringNotContainsString("DB::table('questions')->update", $page);
        $this->assertStringNotContainsString("DB::table('attempts')->update", $page);
    }

    public function test_navigation_and_governance_are_localized_and_truthful(): void
    {
        App::setLocale('en');
        $this->assertSame('Question Quality Review', AssessmentQualityReview::getNavigationLabel());

        App::setLocale('ar');
        $this->assertSame('مراجعة جودة الأسئلة', AssessmentQualityReview::getNavigationLabel());

        App::setLocale('fr');
        $this->assertSame('Qualité des questions', AssessmentQualityReview::getNavigationLabel());

        $matrix = (string) file_get_contents(base_path('../../docs/product/capability-surface-matrix.yaml'));
        $this->assertStringContainsString('id: admin.assessment.question_quality_review', $matrix);
        $this->assertStringContainsString('surface: AssessmentQualityReview / AssessmentQuestionBank', $matrix);
        $this->assertStringContainsString('id: admin.assessment.mistake_bank_operations', $matrix);
        $this->assertStringContainsString('id: admin.assessment.ai_composition', $matrix);
        $this->assertStringContainsString('status: not_implemented_or_activated', $matrix);
        $this->assertStringContainsString('No broad learner answer/history visibility is created for Admin parity.', $matrix);
    }
}

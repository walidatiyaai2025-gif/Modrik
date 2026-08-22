<?php

namespace Tests\Feature;

use App\Filament\Pages\ContentIngestionOperations;
use App\Filament\Pages\ContentOperations;
use App\Filament\Pages\ContentReviewExceptions;
use App\Filament\Pages\ContentTraceability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdminContentOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_content_team_can_open_guided_content_operations_hub(): void
    {
        foreach (['admin', 'content_team'] as $role) {
            $user = User::factory()->create(['role' => $role, 'account_status' => 'active', 'locale' => 'en']);
            $this->actingAs($user);

            Livewire::test(ContentOperations::class)
                ->assertOk()
                ->assertSee('Official content lifecycle')
                ->assertSee('Academic Track')
                ->assertSee('Preparation')
                ->assertSee('Ingestion & Processing')
                ->assertSee('Rights')
                ->assertSee('Review & Publish')
                ->assertSee('Review Exceptions')
                ->assertSee('Traceability & Versions')
                ->assertSee('Publication authority is preserved');

            auth()->logout();
        }
    }

    public function test_student_cannot_access_content_operations_surfaces(): void
    {
        $student = User::factory()->create(['role' => 'student', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($student);

        $this->assertFalse(ContentOperations::canAccess());
        $this->assertFalse(ContentIngestionOperations::canAccess());
        $this->assertFalse(ContentReviewExceptions::canAccess());
        $this->assertFalse(ContentTraceability::canAccess());
    }

    public function test_content_operations_navigation_is_localized_and_rtl_safe(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);

        App::setLocale('en');
        $this->assertSame('Content Operations', ContentOperations::getNavigationLabel());
        $this->assertSame('Ingestion & Processing', ContentIngestionOperations::getNavigationLabel());
        $this->assertSame('Review Exceptions', ContentReviewExceptions::getNavigationLabel());
        $this->assertSame('Traceability & Versions', ContentTraceability::getNavigationLabel());
        App::setLocale('fr');
        $this->assertSame('Opérations de contenu', ContentOperations::getNavigationLabel());
        $this->assertSame('Ingestion et traitement', ContentIngestionOperations::getNavigationLabel());
        $this->assertSame('Exceptions de révision', ContentReviewExceptions::getNavigationLabel());
        $this->assertSame('Traçabilité et versions', ContentTraceability::getNavigationLabel());
        App::setLocale('ar');
        $this->assertSame('عمليات المحتوى', ContentOperations::getNavigationLabel());
        $this->assertSame('الاستيعاب والمعالجة', ContentIngestionOperations::getNavigationLabel());
        $this->assertSame('استثناءات المراجعة', ContentReviewExceptions::getNavigationLabel());
        $this->assertSame('التتبع والإصدارات', ContentTraceability::getNavigationLabel());
        Livewire::test(ContentReviewExceptions::class)->assertSee('dir="rtl"', false);
        Livewire::test(ContentTraceability::class)->assertSee('dir="rtl"', false);
    }

    public function test_ingestion_surface_starts_with_empty_state_and_metrics(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);

        Livewire::test(ContentIngestionOperations::class)
            ->assertOk()
            ->assertSee('Ingestion log')
            ->assertSee('No ingestion activity yet.');

        /** @var ContentIngestionOperations $ingestion */
        $ingestion = Livewire::test(ContentIngestionOperations::class)->instance();
        $metrics = $ingestion->metrics();
        $this->assertSame(['total' => 0, 'processing' => 0, 'blocked' => 0, 'failed' => 0], $metrics);
    }

    public function test_review_exception_surface_derives_real_dry_run_and_blocker_signals(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);
        $this->createImport($admin, [
            'status' => 'staged',
            'rights_review_status' => 'approved',
            'operation_state' => 'blocked',
            'operation_checkpoint' => 'dry_run_blocked',
            'dry_run_summary' => json_encode([
                'publishable' => false,
                'blocking_codes' => ['CONTENT_TARGET_TRACK_MISSING'],
                'counts' => ['questions' => ['create' => 2, 'reuse' => 1, 'conflict' => 1]],
            ], JSON_THROW_ON_ERROR),
        ]);

        /** @var ContentReviewExceptions $page */
        $page = Livewire::test(ContentReviewExceptions::class)
            ->assertOk()
            ->assertSee('Evidence-backed triage')
            ->assertSee('CONTENT_TARGET_TRACK_MISSING')
            ->instance();

        $metrics = $page->metrics();
        $this->assertSame(1, $metrics['total_attention']);
        $this->assertSame(1, $metrics['processing_blocked']);
        $outcomes = $page->dryRunOutcomes();
        $this->assertSame(1, $outcomes['blocked']);
        $this->assertSame(2, $outcomes['question_create']);
        $this->assertSame(1, $outcomes['question_reuse']);
        $this->assertSame(1, $outcomes['question_conflict']);
        $this->assertSame(1, $outcomes['blocking_codes']['CONTENT_TARGET_TRACK_MISSING']);
        $this->assertSame('deferred_disabled', $page->automatedConfidenceStatus()['classification']);
    }

    public function test_traceability_exposes_canonical_versions_and_keeps_rebuild_disabled_without_backend_contract(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);
        $now = now();
        $trackId = (string) Str::ulid();
        $nodeId = (string) Str::ulid();

        DB::table('academic_tracks')->insert([
            'id' => $trackId,
            'code' => 'TEST:TRACK:CONTENT-OPS',
            'board_reference' => null,
            'syllabus_version' => null,
            'year_level' => 'Y6',
            'title' => json_encode(['en' => 'Content Ops Test Track'], JSON_THROW_ON_ERROR),
            'is_fixture' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_nodes')->insert([
            'id' => $nodeId,
            'academic_track_id' => $trackId,
            'parent_id' => null,
            'code' => 'TEST:TOPIC:OPS',
            'type' => 'topic',
            'title' => json_encode(['en' => 'Operations Topic'], JSON_THROW_ON_ERROR),
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('lessons')->insert([
            'id' => (string) Str::ulid(),
            'curriculum_node_id' => $nodeId,
            'slug' => 'versioned-lesson',
            'content_version' => 3,
            'title' => json_encode(['en' => 'Versioned lesson'], JSON_THROW_ON_ERROR),
            'status' => 'published',
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('questions')->insert([
            'id' => (string) Str::ulid(),
            'curriculum_node_id' => $nodeId,
            'content_version' => 4,
            'type' => 'single_choice',
            'prompt' => json_encode(['en' => 'Versioned question'], JSON_THROW_ON_ERROR),
            'options' => null,
            'answer_contract' => json_encode(['answer' => 'a'], JSON_THROW_ON_ERROR),
            'explanation' => json_encode(['en' => 'Fixture explanation'], JSON_THROW_ON_ERROR),
            'maximum_score' => 1,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('quizzes')->insert([
            'id' => (string) Str::ulid(),
            'curriculum_node_id' => $nodeId,
            'kind' => 'practice',
            'blueprint_version' => 5,
            'title' => json_encode(['en' => 'Versioned quiz'], JSON_THROW_ON_ERROR),
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        /** @var ContentTraceability $page */
        $page = Livewire::test(ContentTraceability::class)
            ->assertOk()
            ->assertSee('Traceability chain')
            ->assertSee('Disabled until Backend contract exists')
            ->instance();
        $versions = $page->canonicalVersions();
        $this->assertSame(3, $versions['lessons'][0]['version']);
        $this->assertSame(4, $versions['questions'][0]['version']);
        $this->assertSame(5, $versions['quizzes'][0]['version']);
        $this->assertSame('deferred_disabled', $page->rebuildStatus()['classification']);
    }

    public function test_hub_links_only_to_supported_operator_surfaces_and_marks_missing_backend_capabilities_deferred(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);
        App::setLocale('en');

        /** @var ContentOperations $operations */
        $operations = Livewire::test(ContentOperations::class)->instance();
        $steps = $operations->lifecycle();
        $this->assertCount(5, $steps);
        $this->assertSame(['required', 'active', 'active', 'gate', 'gate'], array_column($steps, 'state'));
        $this->assertNotContains('', array_column($steps, 'url'));
        $supporting = $operations->supportingSurfaces();
        $this->assertCount(2, $supporting);
        $this->assertNotContains('', array_column($supporting, 'url'));
        foreach ($operations->deferredCapabilities() as $capability) {
            $this->assertSame('deferred_disabled', $capability['classification']);
        }
    }

    /** @param array<string, mixed> $overrides */
    private function createImport(User $user, array $overrides = []): string
    {
        $requestId = (string) Str::ulid();
        $importId = (string) Str::ulid();
        $now = now();
        DB::table('preparation_requests')->insert([
            'id' => $requestId,
            'created_by' => (string) $user->getKey(),
            'schema_version' => '1.0.0',
            'settings_hash' => str_repeat('a', 64),
            'normalized_settings' => json_encode(['fixture' => true], JSON_THROW_ON_ERROR),
            'prompt' => 'Synthetic content operations fixture',
            'status' => 'prepared',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('preparation_imports')->insert(array_merge([
            'id' => $importId,
            'preparation_request_id' => $requestId,
            'uploaded_by' => (string) $user->getKey(),
            'archive_hash' => str_repeat('b', 64),
            'status' => 'staged',
            'validation_summary' => json_encode(['accepted' => true], JSON_THROW_ON_ERROR),
            'rights_review_status' => 'pending',
            'operation_state' => 'idle',
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));

        return $importId;
    }
}

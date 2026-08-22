<?php

namespace Tests\Feature;

use App\Filament\Pages\ContentOperations;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdminContentProcessingPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_processing_policy_preserves_zero_paid_path_even_if_optional_runtime_capability_is_enabled(): void
    {
        config()->set('modrik.paid_ai.enabled', true);
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);

        $component = Livewire::test(ContentOperations::class);
        $component
            ->assertOk()
            ->assertSee('Processing policy')
            ->assertSee('Zero-paid path required');

        /** @var ContentOperations $page */
        $page = $component->instance();
        $policy = $page->processingPolicy();
        $this->assertSame('deterministic_preparation_bundle_returned_zip', $policy['mode']);
        $this->assertSame('not_backend_selected', $policy['provider']);
        $this->assertTrue($policy['paid_ai_runtime_enabled']);
        $this->assertFalse($policy['paid_ai_required']);
        $this->assertTrue($policy['zero_paid_fallback']);
        $this->assertSame('backend_content_pack_validator', $policy['validation_authority']);
    }

    public function test_curriculum_coverage_is_derived_from_canonical_tables(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);
        $now = now();
        $trackId = (string) Str::ulid();
        $nodeId = (string) Str::ulid();

        DB::table('academic_tracks')->insert([
            'id' => $trackId,
            'code' => 'TEST:COVERAGE:TRACK',
            'board_reference' => null,
            'syllabus_version' => null,
            'year_level' => 'Y6',
            'title' => json_encode(['en' => 'Coverage Track'], JSON_THROW_ON_ERROR),
            'is_fixture' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_nodes')->insert([
            'id' => $nodeId,
            'academic_track_id' => $trackId,
            'parent_id' => null,
            'code' => 'TEST:COVERAGE:TOPIC',
            'type' => 'topic',
            'title' => json_encode(['en' => 'Coverage Topic'], JSON_THROW_ON_ERROR),
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('lessons')->insert([
            'id' => (string) Str::ulid(),
            'curriculum_node_id' => $nodeId,
            'slug' => 'coverage-lesson',
            'content_version' => 1,
            'title' => json_encode(['en' => 'Coverage Lesson'], JSON_THROW_ON_ERROR),
            'status' => 'published',
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('questions')->insert([
            'id' => (string) Str::ulid(),
            'curriculum_node_id' => $nodeId,
            'content_version' => 1,
            'type' => 'single_choice',
            'prompt' => json_encode(['en' => 'Coverage question'], JSON_THROW_ON_ERROR),
            'options' => null,
            'answer_contract' => json_encode(['answer' => 'a'], JSON_THROW_ON_ERROR),
            'explanation' => json_encode(['en' => 'Coverage explanation'], JSON_THROW_ON_ERROR),
            'maximum_score' => 1,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('quizzes')->insert([
            'id' => (string) Str::ulid(),
            'curriculum_node_id' => $nodeId,
            'kind' => 'practice',
            'blueprint_version' => 1,
            'title' => json_encode(['en' => 'Coverage Quiz'], JSON_THROW_ON_ERROR),
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        /** @var ContentOperations $page */
        $page = Livewire::test(ContentOperations::class)->instance();
        $coverage = $page->coverage();
        $this->assertSame(1, $coverage['tracks']);
        $this->assertSame(1, $coverage['tracks_with_nodes']);
        $this->assertSame(1, $coverage['curriculum_nodes']);
        $this->assertSame(1, $coverage['published_nodes']);
        $this->assertSame(1, $coverage['nodes_with_lessons']);
        $this->assertSame(1, $coverage['nodes_with_questions']);
        $this->assertSame(1, $coverage['nodes_with_quizzes']);
    }
}

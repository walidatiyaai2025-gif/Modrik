<?php

namespace Tests\Feature;

use App\Domain\Learning\AdaptiveLearningContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdaptiveLearningFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_foundation_schema_extends_existing_learning_authority_without_parallel_question_tables(): void
    {
        self::assertTrue(Schema::hasTable('curriculum_nodes'));
        self::assertTrue(Schema::hasTable('questions'));
        self::assertTrue(Schema::hasTable('progress_snapshots'));

        self::assertTrue(Schema::hasTable('learning_objectives'));
        self::assertTrue(Schema::hasTable('student_skill_mastery_states'));

        self::assertTrue(Schema::hasColumns('learning_objectives', [
            'skill_node_id',
            'code',
            'title',
            'description',
            'status',
        ]));

        self::assertTrue(Schema::hasColumns('questions', [
            'learning_objective_id',
            'difficulty',
            'generation_kind',
            'template_contract',
            'source_provenance',
            'review_state',
            'review_version',
            'reviewed_by',
            'reviewed_at',
            'published_by',
            'published_at',
        ]));

        self::assertTrue(Schema::hasColumns('student_skill_mastery_states', [
            'user_id',
            'academic_context_id',
            'skill_node_id',
            'algorithm_version',
            'mastery_score',
            'confidence',
            'evidence_count',
            'state_version',
            'last_evidence_at',
            'calculated_at',
            'archived_at',
        ]));
    }

    public function test_domain_contract_keeps_skill_question_and_mastery_boundaries_explicit(): void
    {
        self::assertContains('skill', AdaptiveLearningContract::CURRICULUM_NODE_TYPES);
        self::assertContains('multiple_choice', AdaptiveLearningContract::QUESTION_TYPES);
        self::assertContains('multi_step_math', AdaptiveLearningContract::QUESTION_TYPES);
        self::assertContains('published', AdaptiveLearningContract::QUESTION_PUBLICATION_STATUSES);
        self::assertContains('suspended', AdaptiveLearningContract::QUESTION_PUBLICATION_STATUSES);
        self::assertSame(['static', 'template'], AdaptiveLearningContract::QUESTION_GENERATION_KINDS);
        self::assertContains('approved', AdaptiveLearningContract::QUESTION_REVIEW_STATES);
    }

    public function test_migration_rollback_drops_question_foreign_key_before_learning_objectives_table(): void
    {
        $path = database_path('migrations/2026_09_18_010000_add_adaptive_learning_foundation.php');
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        $down = strstr($contents, 'public function down(): void');
        self::assertIsString($down);

        $dropForeign = strpos($down, "\$table->dropForeign(['learning_objective_id']);");
        $dropObjectives = strpos($down, "Schema::dropIfExists('learning_objectives');");

        self::assertNotFalse($dropForeign);
        self::assertNotFalse($dropObjectives);
        self::assertTrue($dropForeign < $dropObjectives);
    }
}

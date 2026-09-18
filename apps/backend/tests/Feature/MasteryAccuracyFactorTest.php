<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MasteryAccuracyFactor;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MasteryAccuracyFactorTest extends TestCase
{
    use RefreshDatabase;

    private const SKILL_ID = '01J35600000000000000000001';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LearningSliceSeeder::class);

        $now = now();
        DB::table('curriculum_nodes')->insert([
            'id' => self::SKILL_ID,
            'academic_track_id' => LearningSliceSeeder::TRACK_ID,
            'parent_id' => LearningSliceSeeder::TOPIC_NODE_ID,
            'code' => 'FIXTURE:SKILL:MASTERY-ACCURACY',
            'type' => 'skill',
            'title' => json_encode(['en' => 'Accuracy fixture'], JSON_THROW_ON_ERROR),
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function test_accuracy_uses_latest_graded_revision_and_is_deterministic_and_user_scoped(): void
    {
        $learner = User::query()->findOrFail(LearningSliceSeeder::USER_ID);
        $questionId = DB::table('quiz_questions')
            ->where('quiz_id', LearningSliceSeeder::QUIZ_ID)
            ->value('question_id');
        self::assertIsString($questionId);

        $firstQuestion = '01J35600000000000000000002';
        $secondQuestion = '01J35600000000000000000003';

        $this->insertEvidence(
            userId: LearningSliceSeeder::USER_ID,
            contextId: LearningSliceSeeder::CONTEXT_ID,
            attemptId: '01J35600000000000000000004',
            attemptQuestionId: $firstQuestion,
            questionId: $questionId,
            revisions: [false, true],
        );
        $this->insertEvidence(
            userId: LearningSliceSeeder::USER_ID,
            contextId: LearningSliceSeeder::CONTEXT_ID,
            attemptId: '01J35600000000000000000005',
            attemptQuestionId: $secondQuestion,
            questionId: $questionId,
            revisions: [false],
        );

        $other = User::factory()->create();
        $otherContextId = '01J35600000000000000000006';
        $now = now();
        DB::table('user_academic_contexts')->insert([
            'id' => $otherContextId,
            'user_id' => $other->getKey(),
            'academic_track_id' => LearningSliceSeeder::TRACK_ID,
            'status' => 'active',
            'activated_at' => $now,
            'archived_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insertEvidence(
            userId: (string) $other->getKey(),
            contextId: $otherContextId,
            attemptId: '01J35600000000000000000007',
            attemptQuestionId: '01J35600000000000000000008',
            questionId: $questionId,
            revisions: [true],
        );

        $factor = app(MasteryAccuracyFactor::class);
        $first = $factor->calculate($learner, LearningSliceSeeder::CONTEXT_ID, self::SKILL_ID);
        $second = $factor->calculate($learner, LearningSliceSeeder::CONTEXT_ID, self::SKILL_ID);

        self::assertSame($first, $second);
        self::assertSame(MasteryAccuracyFactor::ALGORITHM_VERSION, $first['algorithm_version']);
        self::assertSame(self::SKILL_ID, $first['skill_node_id']);
        self::assertSame(2, $first['evidence_count']);
        self::assertSame(1, $first['correct_count']);
        self::assertSame(50.0, $first['score']);

        $otherResult = $factor->calculate($other, $otherContextId, self::SKILL_ID);
        self::assertSame(1, $otherResult['evidence_count']);
        self::assertSame(1, $otherResult['correct_count']);
        self::assertSame(100.0, $otherResult['score']);
    }

    /**
     * @param list<bool> $revisions
     */
    private function insertEvidence(
        string $userId,
        string $contextId,
        string $attemptId,
        string $attemptQuestionId,
        string $questionId,
        array $revisions,
    ): void {
        $now = now();

        DB::table('attempts')->insert([
            'id' => $attemptId,
            'user_id' => $userId,
            'academic_context_id' => $contextId,
            'quiz_id' => LearningSliceSeeder::QUIZ_ID,
            'status' => 'graded',
            'seed_encrypted' => 'fixture-seed',
            'seed_fingerprint' => null,
            'blueprint_version' => 1,
            'scope_snapshot' => json_encode([
                'curriculum_node_id' => LearningSliceSeeder::TOPIC_NODE_ID,
                'mode' => 'practice',
            ], JSON_THROW_ON_ERROR),
            'ordering_algorithm' => 'fixture',
            'started_at' => $now,
            'completed_at' => $now,
            'archived_at' => null,
            'score' => 0,
            'max_score' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('attempt_questions')->insert([
            'id' => $attemptQuestionId,
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
            'position' => 1,
            'question_snapshot' => json_encode([
                'schema_version' => 3,
                'skill_node_id' => self::SKILL_ID,
                'type' => 'single_choice',
            ], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($revisions as $index => $correct) {
            DB::table('attempt_answers')->insert([
                'id' => sprintf('01J356%020d', 100 + ((int) substr($attemptQuestionId, -2)) * 10 + $index),
                'attempt_question_id' => $attemptQuestionId,
                'revision' => $index + 1,
                'value' => json_encode(['option_id' => $correct ? 'A' : 'B'], JSON_THROW_ON_ERROR),
                'duration_ms' => 1000,
                'hint_count' => 0,
                'is_correct' => $correct,
                'awarded_score' => $correct ? 1 : 0,
                'graded_at' => $now,
                'answered_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

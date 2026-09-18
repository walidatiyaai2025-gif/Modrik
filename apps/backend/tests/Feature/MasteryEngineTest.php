<?php

namespace Tests\Feature;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use App\Services\MasteryBandPolicy;
use App\Services\MasteryEngine;
use App\Services\SystemSettingsRegistry;
use Carbon\CarbonImmutable;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MasteryEngineTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'modrik-local-fixture-token';

    private const SKILL_ID = '01J35611111111111111111111';

    private const TARGET_TRACK_ID = '01J35611111111111111111112';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'modrik.fixture.enabled' => true,
            'modrik.fixture.bearer_token' => self::TOKEN,
            'modrik.fixture.user_id' => LearningSliceSeeder::USER_ID,
            'modrik.idempotency.secret' => 'test-only-idempotency-secret',
        ]);
        $this->seed(LearningSliceSeeder::class);
        $this->insertSkill();
    }

    public function test_cold_start_is_deterministic_and_display_bands_are_governed_only(): void
    {
        $user = $this->learner();
        $engine = app(MasteryEngine::class);

        $first = $engine->calculate($user, LearningSliceSeeder::CONTEXT_ID, self::SKILL_ID);
        $second = $engine->calculate($user, LearningSliceSeeder::CONTEXT_ID, self::SKILL_ID);

        self::assertSame($first, $second);
        self::assertSame(MasteryEngine::ALGORITHM_VERSION, $first['algorithm_version']);
        self::assertSame(0, $first['evidence_count']);
        self::assertSame(0.0, $first['score_percent']);
        self::assertSame(0.0, $first['confidence']);

        $state = $engine->recalculate($user, LearningSliceSeeder::CONTEXT_ID, self::SKILL_ID);
        self::assertSame('active', $state['state']);
        self::assertSame(1, $state['state_version']);
        self::assertSame('critical', $state['display_band']);

        $registry = app(SystemSettingsRegistry::class);
        $registry->update(
            MasteryBandPolicy::CRITICAL_MAX_KEY,
            'testing',
            10,
            0,
            'Tighten critical display threshold for deterministic mastery test.',
            null,
        );
        $registry->update(
            MasteryBandPolicy::WEAK_MAX_KEY,
            'testing',
            30,
            0,
            'Tighten weak display threshold for deterministic mastery test.',
            null,
        );
        $registry->update(
            MasteryBandPolicy::DEVELOPING_MAX_KEY,
            'testing',
            60,
            0,
            'Tighten developing display threshold for deterministic mastery test.',
            null,
        );

        $afterBandChange = $engine->state($user, LearningSliceSeeder::CONTEXT_ID, self::SKILL_ID, 'testing');
        self::assertSame(0.0, $afterBandChange['score_percent']);
        self::assertSame(1, $afterBandChange['state_version']);
        self::assertSame('critical', $afterBandChange['display_band']);
        self::assertSame(3, DB::table('system_setting_audits')->count());
    }

    public function test_authoritative_signals_are_deterministic_and_recent_performance_can_outweigh_stale_history(): void
    {
        $user = $this->learner();
        $base = CarbonImmutable::parse('2026-09-18T12:00:00+00:00');

        for ($index = 0; $index < 4; $index++) {
            $this->insertEvidence(
                userId: LearningSliceSeeder::USER_ID,
                contextId: LearningSliceSeeder::CONTEXT_ID,
                correct: false,
                difficulty: $index % 2 === 0 ? 'Easy' : 'Medium',
                revisionCount: 1,
                hintCount: 0,
                durationMs: 1000,
                durationTargetMs: 1500,
                gradedAt: $base->subDays(60 - ($index * 8)),
            );
        }

        $this->insertEvidence(
            userId: LearningSliceSeeder::USER_ID,
            contextId: LearningSliceSeeder::CONTEXT_ID,
            correct: true,
            difficulty: 'Hard',
            revisionCount: 3,
            hintCount: 2,
            durationMs: 4000,
            durationTargetMs: 1500,
            gradedAt: $base->subDays(3),
        );
        $this->insertEvidence(
            userId: LearningSliceSeeder::USER_ID,
            contextId: LearningSliceSeeder::CONTEXT_ID,
            correct: true,
            difficulty: 'Exam-style',
            revisionCount: 1,
            hintCount: 0,
            durationMs: 1200,
            durationTargetMs: 1500,
            gradedAt: $base->subDays(2),
        );
        $this->insertEvidence(
            userId: LearningSliceSeeder::USER_ID,
            contextId: LearningSliceSeeder::CONTEXT_ID,
            correct: true,
            difficulty: 'Revision',
            revisionCount: 1,
            hintCount: 1,
            durationMs: 1400,
            durationTargetMs: 1500,
            gradedAt: $base->subDay(),
        );
        $this->insertEvidence(
            userId: LearningSliceSeeder::USER_ID,
            contextId: LearningSliceSeeder::CONTEXT_ID,
            correct: true,
            difficulty: 'Hard',
            revisionCount: 1,
            hintCount: 0,
            durationMs: 1000,
            durationTargetMs: 1500,
            gradedAt: $base,
        );

        $engine = app(MasteryEngine::class);
        $first = $engine->calculate($user, LearningSliceSeeder::CONTEXT_ID, self::SKILL_ID);
        $second = $engine->calculate($user, LearningSliceSeeder::CONTEXT_ID, self::SKILL_ID);

        self::assertSame($first, $second);
        self::assertSame(8, $first['evidence_count']);
        self::assertSame(50.0, $first['factors']['overall_accuracy']);
        self::assertGreaterThan($first['factors']['overall_accuracy'], $first['factors']['recent_accuracy']);
        self::assertGreaterThan($first['factors']['overall_accuracy'], $first['factors']['recency_performance']);
        self::assertLessThan(100.0, $first['factors']['hint_independence']);
        self::assertLessThan(100.0, $first['factors']['duration_performance']);
        self::assertLessThan(100.0, $first['factors']['revision_independence']);
        self::assertGreaterThan(0.0, $first['score_percent']);
        self::assertLessThanOrEqual(100.0, $first['score_percent']);
        self::assertGreaterThan(0.0, $first['confidence']);
        self::assertLessThanOrEqual(1.0, $first['confidence']);
    }

    public function test_recalculation_is_idempotent_and_history_changes_only_when_authoritative_inputs_change(): void
    {
        $user = $this->learner();
        $engine = app(MasteryEngine::class);
        $base = CarbonImmutable::parse('2026-09-18T12:00:00+00:00');

        $this->insertEvidence(
            userId: LearningSliceSeeder::USER_ID,
            contextId: LearningSliceSeeder::CONTEXT_ID,
            correct: true,
            difficulty: 'Medium',
            revisionCount: 1,
            hintCount: 0,
            durationMs: 900,
            durationTargetMs: 1500,
            gradedAt: $base,
        );

        $first = $engine->recalculate($user, LearningSliceSeeder::CONTEXT_ID, self::SKILL_ID);
        $repeat = $engine->recalculate($user, LearningSliceSeeder::CONTEXT_ID, self::SKILL_ID);

        self::assertTrue($first['changed']);
        self::assertFalse($repeat['changed']);
        self::assertSame(1, $first['state_version']);
        self::assertSame(1, $repeat['state_version']);
        self::assertSame(1, DB::table('outbox_events')->where('event_type', 'mastery.state_recalculated')->count());

        $this->insertEvidence(
            userId: LearningSliceSeeder::USER_ID,
            contextId: LearningSliceSeeder::CONTEXT_ID,
            correct: false,
            difficulty: 'Hard',
            revisionCount: 2,
            hintCount: 1,
            durationMs: 2000,
            durationTargetMs: 1500,
            gradedAt: $base->addDay(),
        );

        $changed = $engine->recalculate($user, LearningSliceSeeder::CONTEXT_ID, self::SKILL_ID);
        self::assertTrue($changed['changed']);
        self::assertSame(2, $changed['state_version']);
        self::assertSame(2, $changed['evidence_count']);

        $history = $engine->history($user, LearningSliceSeeder::CONTEXT_ID, self::SKILL_ID);
        self::assertCount(2, $history);
        self::assertSame('mastery.state_recalculated', $history[0]['event_type']);
        self::assertSame(1, $history[0]['payload']['state_version']);
        self::assertSame(2, $history[1]['payload']['state_version']);
        self::assertNotSame(
            $history[0]['payload']['input_fingerprint'],
            $history[1]['payload']['input_fingerprint'],
        );
    }

    public function test_attempt_incremental_update_converges_with_full_context_recalculation(): void
    {
        [$quizId] = $this->insertSkillQuiz();
        $start = $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'mastery-incremental-start-0001')
            ->postJson('/v1/attempts', ['quiz_id' => $quizId])
            ->assertCreated();

        $attemptId = (string) $start->json('data.id');
        $attemptQuestionId = (string) $start->json('data.questions.0.attempt_question_id');
        $answer = (string) $start->json('data.questions.0.response_contract.options.0.id');

        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'mastery-incremental-answer-0001')
            ->putJson('/v1/attempts/'.$attemptId.'/answers/'.$attemptQuestionId, [
                'expected_revision' => 0,
                'value' => $answer,
                'duration_ms' => 1100,
                'hint_count' => 0,
            ])
            ->assertOk();

        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'mastery-incremental-submit-0001')
            ->postJson('/v1/attempts/'.$attemptId.'/submit', [])
            ->assertOk();

        $engine = app(MasteryEngine::class);
        $incremental = $engine->state($this->learner(), LearningSliceSeeder::CONTEXT_ID, self::SKILL_ID, 'testing');
        self::assertSame('active', $incremental['state']);
        self::assertSame(1, $incremental['evidence_count']);
        self::assertSame(1, $incremental['state_version']);

        $eventCount = DB::table('outbox_events')->where('event_type', 'mastery.state_recalculated')->count();
        $full = $engine->recalculateContext($this->learner(), LearningSliceSeeder::CONTEXT_ID);
        self::assertCount(1, $full);

        $afterFull = $engine->state($this->learner(), LearningSliceSeeder::CONTEXT_ID, self::SKILL_ID, 'testing');
        self::assertSame($incremental['score_percent'], $afterFull['score_percent']);
        self::assertSame($incremental['confidence'], $afterFull['confidence']);
        self::assertSame($incremental['evidence_count'], $afterFull['evidence_count']);
        self::assertSame($incremental['state_version'], $afterFull['state_version']);
        self::assertSame($eventCount, DB::table('outbox_events')->where('event_type', 'mastery.state_recalculated')->count());
    }

    public function test_cross_user_scope_fails_closed_and_context_reset_archives_mastery_history(): void
    {
        $user = $this->learner();
        $engine = app(MasteryEngine::class);
        $this->insertEvidence(
            userId: LearningSliceSeeder::USER_ID,
            contextId: LearningSliceSeeder::CONTEXT_ID,
            correct: true,
            difficulty: 'Medium',
            revisionCount: 1,
            hintCount: 0,
            durationMs: 1000,
            durationTargetMs: null,
            gradedAt: CarbonImmutable::parse('2026-09-18T12:00:00+00:00'),
        );
        $engine->recalculate($user, LearningSliceSeeder::CONTEXT_ID, self::SKILL_ID);

        $other = User::factory()->create();
        try {
            $engine->state($other, LearningSliceSeeder::CONTEXT_ID, self::SKILL_ID);
            self::fail('Foreign user must not read another learner mastery state.');
        } catch (ApiProblemException $exception) {
            self::assertSame(404, $exception->status);
            self::assertSame('RESOURCE_NOT_FOUND', $exception->problemCode);
        }

        $this->insertTargetTrack();
        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'mastery-context-reset-0001')
            ->postJson('/v1/academic-context/reset', ['academic_track_id' => self::TARGET_TRACK_ID])
            ->assertOk();

        $archived = DB::table('student_skill_mastery_states')
            ->where('user_id', LearningSliceSeeder::USER_ID)
            ->where('academic_context_id', LearningSliceSeeder::CONTEXT_ID)
            ->where('skill_node_id', self::SKILL_ID)
            ->first(['id', 'archived_at', 'state_version']);
        self::assertNotNull($archived);
        self::assertNotNull($archived->archived_at);
        self::assertSame(1, (int) $archived->state_version);

        $history = $engine->history($user, LearningSliceSeeder::CONTEXT_ID, self::SKILL_ID);
        self::assertCount(2, $history);
        self::assertSame('mastery.state_recalculated', $history[0]['event_type']);
        self::assertSame('mastery.state_archived', $history[1]['event_type']);
        self::assertSame('academic_context_reset', $history[1]['payload']['reason']);

        $resetPayload = DB::table('outbox_events')->where('event_type', 'academic.context_reset')->value('payload');
        self::assertIsString($resetPayload);
        $decoded = json_decode($resetPayload, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame(1, $decoded['archived_mastery_count']);
    }

    private function learner(): User
    {
        return User::query()->findOrFail(LearningSliceSeeder::USER_ID);
    }

    private function insertSkill(): void
    {
        DB::table('curriculum_nodes')->insert([
            'id' => self::SKILL_ID,
            'academic_track_id' => LearningSliceSeeder::TRACK_ID,
            'parent_id' => LearningSliceSeeder::TOPIC_NODE_ID,
            'code' => 'FIXTURE:SKILL:MASTERY-COMPLETE',
            'type' => 'skill',
            'title' => json_encode(['en' => 'Mastery fixture'], JSON_THROW_ON_ERROR),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertEvidence(
        string $userId,
        string $contextId,
        bool $correct,
        string $difficulty,
        int $revisionCount,
        int $hintCount,
        int $durationMs,
        ?int $durationTargetMs,
        CarbonImmutable $gradedAt,
    ): void {
        $attemptId = (string) Str::ulid();
        $attemptQuestionId = (string) Str::ulid();
        $questionId = (string) DB::table('quiz_questions')
            ->where('quiz_id', LearningSliceSeeder::QUIZ_ID)
            ->value('question_id');

        DB::table('attempts')->insert([
            'id' => $attemptId,
            'user_id' => $userId,
            'academic_context_id' => $contextId,
            'quiz_id' => LearningSliceSeeder::QUIZ_ID,
            'status' => 'graded',
            'seed_encrypted' => 'mastery-fixture-seed',
            'seed_fingerprint' => null,
            'blueprint_version' => 1,
            'scope_snapshot' => json_encode([
                'curriculum_node_id' => LearningSliceSeeder::TOPIC_NODE_ID,
                'mode' => 'practice',
            ], JSON_THROW_ON_ERROR),
            'ordering_algorithm' => 'mastery-fixture',
            'started_at' => $gradedAt->subMinute(),
            'completed_at' => $gradedAt,
            'archived_at' => null,
            'score' => $correct ? 1 : 0,
            'max_score' => 1,
            'created_at' => $gradedAt,
            'updated_at' => $gradedAt,
        ]);

        DB::table('attempt_questions')->insert([
            'id' => $attemptQuestionId,
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
            'position' => 1,
            'question_snapshot' => json_encode([
                'schema_version' => 3,
                'skill_node_id' => self::SKILL_ID,
                'difficulty' => $difficulty,
                'type' => 'single_choice',
                'maximum_score' => 1,
                'assessment_metadata' => $durationTargetMs === null
                    ? []
                    : ['mastery_duration_target_ms' => $durationTargetMs],
            ], JSON_THROW_ON_ERROR),
            'created_at' => $gradedAt,
            'updated_at' => $gradedAt,
        ]);

        for ($revision = 1; $revision <= $revisionCount; $revision++) {
            $isLatest = $revision === $revisionCount;
            DB::table('attempt_answers')->insert([
                'id' => (string) Str::ulid(),
                'attempt_question_id' => $attemptQuestionId,
                'revision' => $revision,
                'value' => json_encode(['option_id' => $correct ? 'A' : 'B'], JSON_THROW_ON_ERROR),
                'duration_ms' => $durationMs,
                'hint_count' => $hintCount,
                'is_correct' => $isLatest ? $correct : null,
                'awarded_score' => $isLatest ? ($correct ? 1 : 0) : null,
                'graded_at' => $isLatest ? $gradedAt : null,
                'answered_at' => $gradedAt->subSeconds($revisionCount - $revision),
                'created_at' => $gradedAt,
                'updated_at' => $gradedAt,
            ]);
        }
    }

    /** @return array{0:string,1:string} */
    private function insertSkillQuiz(): array
    {
        $questionId = (string) Str::ulid();
        $quizId = (string) Str::ulid();
        $now = now();

        DB::table('questions')->insert([
            'id' => $questionId,
            'curriculum_node_id' => self::SKILL_ID,
            'learning_objective_id' => null,
            'content_version' => 1,
            'type' => 'multiple_choice',
            'difficulty' => 'Hard',
            'generation_kind' => 'static',
            'template_contract' => null,
            'source_provenance' => json_encode(['source_id' => 'mastery-test', 'source_page' => 1], JSON_THROW_ON_ERROR),
            'review_state' => 'approved',
            'review_version' => 1,
            'prompt' => json_encode(['en' => 'Choose A'], JSON_THROW_ON_ERROR),
            'options' => json_encode([
                ['id' => 'A', 'label' => ['en' => 'A']],
                ['id' => 'B', 'label' => ['en' => 'B']],
            ], JSON_THROW_ON_ERROR),
            'answer_contract' => json_encode(['correct_option_id' => 'A'], JSON_THROW_ON_ERROR),
            'explanation' => json_encode(['en' => 'A is correct'], JSON_THROW_ON_ERROR),
            'maximum_score' => 1,
            'assessment_metadata' => json_encode([
                'hints' => ['Use the source'],
                'mastery_duration_target_ms' => 1500,
            ], JSON_THROW_ON_ERROR),
            'option_shuffle_safe' => false,
            'status' => 'published',
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('quizzes')->insert([
            'id' => $quizId,
            'curriculum_node_id' => self::SKILL_ID,
            'kind' => 'practice',
            'blueprint_version' => 1,
            'blueprint' => null,
            'title' => json_encode(['en' => 'Mastery integration'], JSON_THROW_ON_ERROR),
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('quiz_questions')->insert([
            'quiz_id' => $quizId,
            'question_id' => $questionId,
            'source_position' => 1,
        ]);

        return [$quizId, $questionId];
    }

    private function insertTargetTrack(): void
    {
        DB::table('academic_tracks')->insert([
            'id' => self::TARGET_TRACK_ID,
            'code' => 'FIXTURE:MASTERY:RESET-TARGET',
            'year_level' => 'FIXTURE-YEAR-8',
            'title' => json_encode([
                'ar' => 'مسار هدف لاختبار إتقان',
                'en' => 'Mastery reset target',
                'fr' => 'Parcours cible pour la maîtrise',
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'is_fixture' => true,
            'availability_state' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

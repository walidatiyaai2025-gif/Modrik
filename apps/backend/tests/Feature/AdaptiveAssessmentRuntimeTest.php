<?php

namespace Tests\Feature;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use App\Services\AssessmentModePolicy;
use App\Services\AttemptService;
use Carbon\CarbonImmutable;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdaptiveAssessmentRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'modrik-local-fixture-token';

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
    }

    public function test_all_authorized_modes_share_one_authoritative_runtime_policy(): void
    {
        $policy = app(AssessmentModePolicy::class);

        foreach (AssessmentModePolicy::MODES as $mode) {
            $resolved = $policy->forQuizKind($mode);
            self::assertSame($mode, $resolved['mode']);
            self::assertSame('after_submit', $resolved['reveal_policy']);
            self::assertSame(! in_array($mode, ['diagnostic', 'exam'], true), $resolved['hints_allowed']);
        }

        self::assertSame('exam', $policy->forQuizKind('mock_exam')['mode']);
    }

    public function test_exam_mode_blocks_hints_hides_answers_until_submit_and_persists_telemetry(): void
    {
        DB::table('quizzes')->where('id', LearningSliceSeeder::QUIZ_ID)->update(['kind' => 'exam']);

        $start = $this->start(LearningSliceSeeder::QUIZ_ID, 'adaptive-exam-start-0001')
            ->assertCreated()
            ->assertJsonPath('data.mode', 'exam')
            ->assertJsonPath('data.hints_allowed', false)
            ->assertJsonPath('data.reveal_policy', 'after_submit');

        $body = $start->getContent();
        self::assertIsString($body);
        self::assertStringNotContainsString('grading_contract', $body);
        self::assertStringNotContainsString('correct_answer', $body);
        self::assertStringNotContainsString('explanation', $body);

        /** @var list<array<string, mixed>> $questions */
        $questions = $start->json('data.questions');
        $question = $questions[0];
        $attemptId = (string) $start->json('data.id');
        $attemptQuestionId = (string) $question['attempt_question_id'];
        $value = $this->answerValue($question);

        $this->answer($attemptId, $attemptQuestionId, $value, 'adaptive-exam-hint-0001', 1200, 1)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'HINTS_NOT_ALLOWED');

        $this->answer($attemptId, $attemptQuestionId, $value, 'adaptive-exam-answer-0001', 1200, 0)
            ->assertOk()
            ->assertJsonPath('data.duration_ms', 1200)
            ->assertJsonPath('data.hint_count', 0);

        $result = $this->submit($attemptId, 'adaptive-exam-submit-0001')
            ->assertOk()
            ->assertJsonPath('data.attempt.status', 'graded');

        self::assertNotNull($result->json('data.review.0.correct_answer'));
        self::assertNotNull($result->json('data.review.0.explanation'));
        $this->assertDatabaseHas('attempt_answers', [
            'attempt_question_id' => $attemptQuestionId,
            'duration_ms' => 1200,
            'hint_count' => 0,
        ]);
    }

    public function test_canonical_multiple_choice_is_single_answer_and_submit_retry_rereads_persisted_result(): void
    {
        [$quizId] = $this->insertQuestionAndQuiz(
            type: 'multiple_choice',
            answerContract: ['correct_option_id' => 'A'],
            options: [
                ['id' => 'A', 'label' => ['en' => 'One', 'ar' => 'واحد', 'fr' => 'Un']],
                ['id' => 'B', 'label' => ['en' => 'Two', 'ar' => 'اثنان', 'fr' => 'Deux']],
            ],
        );

        $start = $this->start($quizId, 'adaptive-mcq-start-0001')
            ->assertCreated()
            ->assertJsonPath('data.questions.0.response_contract.kind', 'single_choice');

        $attemptId = (string) $start->json('data.id');
        $questionId = (string) $start->json('data.questions.0.attempt_question_id');
        $this->answer($attemptId, $questionId, 'A', 'adaptive-mcq-answer-0001', 800, 1)->assertOk();

        $first = $this->submit($attemptId, 'adaptive-mcq-submit-0001')
            ->assertOk()
            ->assertJsonPath('data.score', 1)
            ->assertJsonPath('data.review.0.correct', true);

        $eventCount = DB::table('outbox_events')
            ->where('aggregate_id', $attemptId)
            ->where('event_type', 'assessment.attempt_submitted')
            ->count();

        $second = $this->submit($attemptId, 'adaptive-mcq-submit-0001')
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.score', 1)
            ->assertJsonPath('data.review.0.correct', true);

        self::assertSame($first->json('data.max_score'), $second->json('data.max_score'));
        self::assertSame(
            $eventCount,
            DB::table('outbox_events')
                ->where('aggregate_id', $attemptId)
                ->where('event_type', 'assessment.attempt_submitted')
                ->count(),
        );
    }

    public function test_answer_success_payload_is_authoritatively_reread_from_persistence(): void
    {
        $start = $this->start(LearningSliceSeeder::QUIZ_ID, 'adaptive-answer-reread-start-0001')
            ->assertCreated();

        /** @var list<array<string, mixed>> $questions */
        $questions = $start->json('data.questions');
        $question = $questions[0];
        $attemptId = (string) $start->json('data.id');
        $attemptQuestionId = (string) $question['attempt_question_id'];
        $value = $this->answerValue($question);

        $response = $this->answer(
            $attemptId,
            $attemptQuestionId,
            $value,
            'adaptive-answer-reread-record-0001',
            2345,
            0,
        )
            ->assertOk()
            ->assertJsonPath('data.revision', 1)
            ->assertJsonPath('data.duration_ms', 2345)
            ->assertJsonPath('data.hint_count', 0);

        $stored = DB::table('attempt_answers')
            ->where('attempt_question_id', $attemptQuestionId)
            ->where('revision', 1)
            ->first(['revision', 'value', 'duration_ms', 'hint_count', 'answered_at']);

        self::assertNotNull($stored);
        self::assertSame((int) $stored->revision, $response->json('data.revision'));
        self::assertSame(
            json_decode((string) $stored->value, true, flags: JSON_THROW_ON_ERROR),
            $response->json('data.value'),
        );
        self::assertSame((int) $stored->duration_ms, $response->json('data.duration_ms'));
        self::assertSame((int) $stored->hint_count, $response->json('data.hint_count'));
        self::assertSame(
            CarbonImmutable::parse((string) $stored->answered_at)->toIso8601String(),
            $response->json('data.answered_at'),
        );
        $this->assertDatabaseHas('outbox_events', [
            'aggregate_id' => $attemptId,
            'event_type' => 'assessment.answer_recorded',
        ]);
    }

    public function test_answer_retry_replays_single_authoritative_revision_without_duplicate_event(): void
    {
        $start = $this->start(LearningSliceSeeder::QUIZ_ID, 'adaptive-answer-idempotency-start-0001')
            ->assertCreated();

        /** @var list<array<string, mixed>> $questions */
        $questions = $start->json('data.questions');
        $question = $questions[0];
        $attemptId = (string) $start->json('data.id');
        $attemptQuestionId = (string) $question['attempt_question_id'];
        $value = $this->answerValue($question);
        $key = 'adaptive-answer-idempotency-record-0001';

        $first = $this->answer($attemptId, $attemptQuestionId, $value, $key, 1750, 0)
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.revision', 1);

        $stored = DB::table('attempt_answers')
            ->where('attempt_question_id', $attemptQuestionId)
            ->where('revision', 1)
            ->first(['revision', 'value', 'duration_ms', 'hint_count', 'answered_at']);

        self::assertNotNull($stored);
        self::assertSame((int) $stored->revision, $first->json('data.revision'));
        self::assertSame(
            json_decode((string) $stored->value, true, flags: JSON_THROW_ON_ERROR),
            $first->json('data.value'),
        );
        self::assertSame((int) $stored->duration_ms, $first->json('data.duration_ms'));
        self::assertSame((int) $stored->hint_count, $first->json('data.hint_count'));
        self::assertSame(
            CarbonImmutable::parse((string) $stored->answered_at)->toIso8601String(),
            $first->json('data.answered_at'),
        );

        $second = $this->answer($attemptId, $attemptQuestionId, $value, $key, 1750, 0)
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.revision', 1);

        self::assertSame($first->json('data'), $second->json('data'));
        self::assertSame(
            1,
            DB::table('attempt_answers')
                ->where('attempt_question_id', $attemptQuestionId)
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('outbox_events')
                ->where('aggregate_id', $attemptId)
                ->where('event_type', 'assessment.answer_recorded')
                ->count(),
        );
    }

    public function test_same_attempt_resume_uses_immutable_snapshots_and_latest_authoritative_answer(): void
    {
        $start = $this->start(LearningSliceSeeder::QUIZ_ID, 'adaptive-resume-start-0001')
            ->assertCreated();

        /** @var list<array<string, mixed>> $initialQuestions */
        $initialQuestions = $start->json('data.questions');
        self::assertNotEmpty($initialQuestions);

        $attemptId = (string) $start->json('data.id');
        $firstQuestion = $initialQuestions[0];
        $attemptQuestionId = (string) $firstQuestion['attempt_question_id'];
        $value = $this->answerValue($firstQuestion);

        $answer = $this->answer(
            $attemptId,
            $attemptQuestionId,
            $value,
            'adaptive-resume-answer-0001',
            2468,
            0,
        )
            ->assertOk()
            ->assertJsonPath('data.revision', 1)
            ->assertJsonPath('data.duration_ms', 2468)
            ->assertJsonPath('data.hint_count', 0);

        $storedAnswer = DB::table('attempt_answers')
            ->where('attempt_question_id', $attemptQuestionId)
            ->where('revision', 1)
            ->first(['revision', 'value', 'duration_ms', 'hint_count', 'answered_at']);

        self::assertNotNull($storedAnswer);
        self::assertSame((int) $storedAnswer->revision, $answer->json('data.revision'));
        self::assertSame(
            json_decode((string) $storedAnswer->value, true, flags: JSON_THROW_ON_ERROR),
            $answer->json('data.value'),
        );
        self::assertSame((int) $storedAnswer->duration_ms, $answer->json('data.duration_ms'));
        self::assertSame((int) $storedAnswer->hint_count, $answer->json('data.hint_count'));
        self::assertSame(
            CarbonImmutable::parse((string) $storedAnswer->answered_at)->toIso8601String(),
            $answer->json('data.answered_at'),
        );

        $attemptQuestion = DB::table('attempt_questions')
            ->where('id', $attemptQuestionId)
            ->where('attempt_id', $attemptId)
            ->first(['question_id', 'question_snapshot']);
        self::assertNotNull($attemptQuestion);

        $snapshotBeforeSourceMutation = json_decode(
            (string) $attemptQuestion->question_snapshot,
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($snapshotBeforeSourceMutation);

        $originalMode = (string) $start->json('data.mode');
        $mutatedQuizKind = $originalMode === 'exam' ? 'practice' : 'exam';
        $originalBlueprintVersion = (int) $start->json('data.blueprint_version');

        DB::table('quizzes')
            ->where('id', LearningSliceSeeder::QUIZ_ID)
            ->update([
                'kind' => $mutatedQuizKind,
                'blueprint_version' => $originalBlueprintVersion + 100,
                'updated_at' => now(),
            ]);

        DB::table('questions')
            ->where('id', (string) $attemptQuestion->question_id)
            ->update([
                'content_version' => ((int) ($snapshotBeforeSourceMutation['content_version'] ?? 1)) + 100,
                'difficulty' => 'Exam-style',
                'prompt' => json_encode(
                    ['en' => 'MUTATED AFTER START', 'ar' => 'تغير بعد البدء', 'fr' => 'MODIFIÉ APRÈS DÉMARRAGE'],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                ),
                'answer_contract' => json_encode(['correct_option_id' => 'mutated'], JSON_THROW_ON_ERROR),
                'explanation' => json_encode(
                    ['en' => 'Mutated', 'ar' => 'متغير', 'fr' => 'Modifié'],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                ),
                'assessment_metadata' => json_encode(['hints' => ['Mutated hint']], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

        $resumed = $this->withToken(self::TOKEN)
            ->getJson('/v1/attempts/'.$attemptId)
            ->assertOk()
            ->assertJsonPath('data.id', $attemptId)
            ->assertJsonPath('data.mode', $originalMode)
            ->assertJsonPath('data.hints_allowed', $start->json('data.hints_allowed'))
            ->assertJsonPath('data.reveal_policy', $start->json('data.reveal_policy'))
            ->assertJsonPath('data.blueprint_version', $originalBlueprintVersion)
            ->assertJsonPath('data.ordering_algorithm', $start->json('data.ordering_algorithm'));

        /** @var list<array<string, mixed>> $resumedQuestions */
        $resumedQuestions = $resumed->json('data.questions');
        self::assertCount(count($initialQuestions), $resumedQuestions);

        foreach ($initialQuestions as $index => $initialQuestion) {
            foreach ([
                'attempt_question_id',
                'position',
                'type',
                'difficulty',
                'skill_node_id',
                'prompt',
                'response_contract',
                'hints',
            ] as $field) {
                self::assertSame(
                    $initialQuestion[$field] ?? null,
                    $resumedQuestions[$index][$field] ?? null,
                    "Resumed attempt drifted from the persisted question snapshot for {$field}.",
                );
            }
        }

        $resumedAnswer = $resumedQuestions[0]['current_answer'] ?? null;
        self::assertIsArray($resumedAnswer);
        self::assertSame((int) $storedAnswer->revision, $resumedAnswer['revision'] ?? null);
        self::assertSame(
            json_decode((string) $storedAnswer->value, true, flags: JSON_THROW_ON_ERROR),
            $resumedAnswer['value'] ?? null,
        );
        self::assertSame((int) $storedAnswer->duration_ms, $resumedAnswer['duration_ms'] ?? null);
        self::assertSame((int) $storedAnswer->hint_count, $resumedAnswer['hint_count'] ?? null);
        self::assertSame(
            CarbonImmutable::parse((string) $storedAnswer->answered_at)->toIso8601String(),
            $resumedAnswer['answered_at'] ?? null,
        );

        $this->assertDatabaseHas('quizzes', [
            'id' => LearningSliceSeeder::QUIZ_ID,
            'kind' => $mutatedQuizKind,
            'blueprint_version' => $originalBlueprintVersion + 100,
        ]);
        self::assertNotSame('MUTATED AFTER START', $resumedQuestions[0]['prompt']['en'] ?? null);
    }

    public function test_template_question_materializes_deterministically_without_runtime_ai(): void
    {
        [$quizId] = $this->insertQuestionAndQuiz(
            type: 'numeric',
            answerContract: ['value' => 0, 'tolerance' => 0],
            options: null,
            generationKind: 'template',
            templateContract: [
                'kind' => 'arithmetic_v1',
                'operator' => 'add',
                'left' => ['min' => 2, 'max' => 9],
                'right' => ['min' => 2, 'max' => 9],
            ],
            prompt: ['en' => '{{left}} + {{right}} = ?', 'ar' => '{{left}} + {{right}} = ؟', 'fr' => '{{left}} + {{right}} = ?'],
        );

        $start = $this->start($quizId, 'adaptive-template-start-0001')
            ->assertCreated()
            ->assertJsonPath('data.questions.0.response_contract.kind', 'numeric');

        $prompt = (string) $start->json('data.questions.0.prompt.en');
        self::assertDoesNotMatchRegularExpression('/\{\{(?:left|right)\}\}/', $prompt);
        $matched = preg_match('/(-?\d+) \+ (-?\d+)/', $prompt, $matches);
        self::assertSame(1, $matched);
        if (! isset($matches[1], $matches[2])) {
            self::fail('Materialized arithmetic prompt must expose both deterministic operands.');
        }
        $answer = (int) $matches[1] + (int) $matches[2];

        $attemptId = (string) $start->json('data.id');
        $attemptQuestionId = (string) $start->json('data.questions.0.attempt_question_id');
        $this->answer($attemptId, $attemptQuestionId, $answer, 'adaptive-template-answer-0001', 500, 0)->assertOk();

        $this->submit($attemptId, 'adaptive-template-submit-0001')
            ->assertOk()
            ->assertJsonPath('data.score', 1)
            ->assertJsonPath('data.review.0.correct', true);
    }

    public function test_foreign_user_cannot_mutate_answer_by_direct_ids(): void
    {
        $start = $this->start(LearningSliceSeeder::QUIZ_ID, 'adaptive-owner-answer-idor-start-0001')->assertCreated();

        /** @var list<array<string, mixed>> $questions */
        $questions = $start->json('data.questions');
        $question = $questions[0];
        $attemptId = (string) $start->json('data.id');
        $attemptQuestionId = (string) $question['attempt_question_id'];
        $value = $this->answerValue($question);
        $foreignUser = User::factory()->create();

        try {
            app(AttemptService::class)->recordAnswer(
                $foreignUser,
                $attemptId,
                $attemptQuestionId,
                0,
                $value,
                900,
                0,
            );
            self::fail('Foreign user must not mutate another user\'s attempt answer by direct IDs.');
        } catch (ApiProblemException $exception) {
            self::assertSame(404, $exception->status);
            self::assertSame('RESOURCE_NOT_FOUND', $exception->problemCode);
        }

        $this->assertDatabaseMissing('attempt_answers', [
            'attempt_question_id' => $attemptQuestionId,
        ]);
        $this->assertDatabaseMissing('outbox_events', [
            'aggregate_id' => $attemptId,
            'event_type' => 'assessment.answer_recorded',
        ]);
    }

    public function test_result_and_direct_ids_fail_closed_across_users(): void
    {
        $start = $this->start(LearningSliceSeeder::QUIZ_ID, 'adaptive-owner-start-0001')->assertCreated();
        $attemptId = (string) $start->json('data.id');
        $this->submit($attemptId, 'adaptive-owner-submit-0001')->assertOk();

        $other = User::factory()->create();
        try {
            app(AttemptService::class)->result($other, $attemptId);
            self::fail('Foreign user must not read another user\'s attempt result.');
        } catch (ApiProblemException $exception) {
            self::assertSame(404, $exception->status);
            self::assertSame('RESOURCE_NOT_FOUND', $exception->problemCode);
        }
    }

    /** @return TestResponse<JsonResponse> */
    private function start(string $quizId, string $key): TestResponse
    {
        return $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/v1/attempts', ['quiz_id' => $quizId]);
    }

    /** @return TestResponse<JsonResponse> */
    private function answer(string $attemptId, string $questionId, mixed $value, string $key, int $durationMs, int $hintCount): TestResponse
    {
        return $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $key)
            ->putJson('/v1/attempts/'.$attemptId.'/answers/'.$questionId, [
                'expected_revision' => 0,
                'value' => $value,
                'duration_ms' => $durationMs,
                'hint_count' => $hintCount,
            ]);
    }

    /** @return TestResponse<JsonResponse> */
    private function submit(string $attemptId, string $key): TestResponse
    {
        return $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/v1/attempts/'.$attemptId.'/submit', []);
    }

    /** @param  array<string, mixed>  $question */
    private function answerValue(array $question): mixed
    {
        $contract = $question['response_contract'];
        if (($contract['kind'] ?? null) === 'single_choice') {
            return $contract['options'][0]['id'];
        }
        if (($contract['kind'] ?? null) === 'short_text') {
            return 'review';
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $answerContract
     * @param  list<array<string, mixed>>|null  $options
     * @param  array<string, mixed>|null  $templateContract
     * @param  array<string, string>|null  $prompt
     * @return array{0: string, 1: string}
     */
    private function insertQuestionAndQuiz(
        string $type,
        array $answerContract,
        ?array $options,
        string $generationKind = 'static',
        ?array $templateContract = null,
        ?array $prompt = null,
    ): array {
        $questionId = (string) Str::ulid();
        $quizId = (string) Str::ulid();
        $now = now();

        DB::table('questions')->insert([
            'id' => $questionId,
            'curriculum_node_id' => LearningSliceSeeder::TOPIC_NODE_ID,
            'learning_objective_id' => null,
            'content_version' => 1,
            'type' => $type,
            'difficulty' => 'Easy',
            'generation_kind' => $generationKind,
            'template_contract' => $templateContract === null ? null : json_encode($templateContract, JSON_THROW_ON_ERROR),
            'source_provenance' => json_encode(['source_id' => 'test', 'source_page' => null], JSON_THROW_ON_ERROR),
            'review_state' => 'approved',
            'review_version' => 1,
            'prompt' => json_encode($prompt ?? ['en' => 'Choose one', 'ar' => 'اختر واحداً', 'fr' => 'Choisissez'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'options' => $options === null ? null : json_encode($options, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'answer_contract' => json_encode($answerContract, JSON_THROW_ON_ERROR),
            'explanation' => json_encode(['en' => 'Explained', 'ar' => 'شرح', 'fr' => 'Explication'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'maximum_score' => 1,
            'assessment_metadata' => json_encode(['hints' => ['Think carefully']], JSON_THROW_ON_ERROR),
            'option_shuffle_safe' => false,
            'status' => 'published',
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('quizzes')->insert([
            'id' => $quizId,
            'curriculum_node_id' => LearningSliceSeeder::TOPIC_NODE_ID,
            'kind' => 'practice',
            'blueprint_version' => 1,
            'blueprint' => null,
            'title' => json_encode(['en' => 'Adaptive runtime test', 'ar' => 'اختبار', 'fr' => 'Test'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
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
}

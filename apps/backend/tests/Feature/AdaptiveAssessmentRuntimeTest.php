<?php

namespace Tests\Feature;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use App\Services\AssessmentModePolicy;
use App\Services\AttemptService;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        preg_match('/(-?\d+) \+ (-?\d+)/', $prompt, $matches);
        self::assertCount(3, $matches);
        $answer = (int) $matches[1] + (int) $matches[2];

        $attemptId = (string) $start->json('data.id');
        $attemptQuestionId = (string) $start->json('data.questions.0.attempt_question_id');
        $this->answer($attemptId, $attemptQuestionId, $answer, 'adaptive-template-answer-0001', 500, 0)->assertOk();

        $this->submit($attemptId, 'adaptive-template-submit-0001')
            ->assertOk()
            ->assertJsonPath('data.score', 1)
            ->assertJsonPath('data.review.0.correct', true);
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

    private function start(string $quizId, string $key)
    {
        return $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/v1/attempts', ['quiz_id' => $quizId]);
    }

    private function answer(string $attemptId, string $questionId, mixed $value, string $key, int $durationMs, int $hintCount)
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

    private function submit(string $attemptId, string $key)
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

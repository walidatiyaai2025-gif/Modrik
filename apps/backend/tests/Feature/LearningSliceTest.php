<?php

namespace Tests\Feature;

use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class LearningSliceTest extends TestCase
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

    public function test_fixture_learning_reads_are_authenticated_scoped_and_multilingual(): void
    {
        config(['modrik.fixture.enabled' => false]);
        $this->assertFalse((bool) config('modrik.fixture.enabled'));
        $this->withToken(self::TOKEN)->getJson('/v1/session')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'AUTHENTICATION_REQUIRED');
        config(['modrik.fixture.enabled' => true]);
        $this->withoutHeader('Authorization');

        $this->getJson('/v1/session')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'AUTHENTICATION_REQUIRED')
            ->assertJsonStructure(['type', 'title', 'status', 'code', 'request_id', 'retryable']);

        $this->withToken(self::TOKEN)->getJson('/v1/session')
            ->assertOk()
            ->assertJsonPath('data.user_id', LearningSliceSeeder::USER_ID)
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.roles.0', 'student');

        $this->withToken(self::TOKEN)->getJson('/v1/academic-context')
            ->assertOk()
            ->assertJsonPath('data.state', 'active')
            ->assertJsonPath('data.context_id', LearningSliceSeeder::CONTEXT_ID)
            ->assertJsonPath('data.academic_track_id', LearningSliceSeeder::TRACK_ID)
            ->assertJsonPath('data.year_level', 'FIXTURE-YEAR-6-7');

        $lesson = $this->withToken(self::TOKEN)->getJson('/v1/lessons/01J00000000000000000000003')
            ->assertOk()
            ->assertJsonPath('data.practice_quiz_id', '01J00000000000000000000020')
            ->assertJsonPath('data.title.en', 'Build a simple study plan')
            ->assertJsonPath('data.title.ar', 'أنشئ خطة مذاكرة بسيطة')
            ->assertJsonPath('data.title.fr', "Créer un plan d'étude simple")
            ->assertJsonCount(2, 'data.blocks');

        $lessonContent = $this->responseContent($lesson);
        $this->assertStringNotContainsString('correct_option_id', $lessonContent);
        $this->assertStringNotContainsString('accepted_answers', $lessonContent);
    }

    public function test_new_attempts_are_server_seeded_non_static_idempotent_and_immutable_on_resume(): void
    {
        $sourceOrder = DB::table('quiz_questions')
            ->where('quiz_id', '01J00000000000000000000020')
            ->orderBy('source_position')
            ->pluck('question_id')
            ->all();

        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'reject-client-control-0001')
            ->postJson('/v1/attempts', [
                'quiz_id' => '01J00000000000000000000020',
                'seed' => 'client-seed',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('errors.0.code', 'FIELD_NOT_ALLOWED');
        $this->assertDatabaseCount('attempts', 0);

        $key = 'start-attempt-command-0001';
        $start = $this->startAttempt($key)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.ordering_algorithm', 'modrik-fy-v1')
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonCount(3, 'data.questions');

        $attemptId = (string) $start->json('data.id');
        $orderedQuestionIds = DB::table('attempt_questions')
            ->where('attempt_id', $attemptId)
            ->orderBy('position')
            ->pluck('question_id')
            ->all();
        $this->assertNotSame($sourceOrder, $orderedQuestionIds);
        $startContent = $this->responseContent($start);
        $this->assertStringNotContainsString('seed', $startContent);
        $this->assertStringNotContainsString('correct_option_id', $startContent);

        $encryptedSeed = DB::table('attempts')->where('id', $attemptId)->value('seed_encrypted');
        $this->assertIsString($encryptedSeed);
        $seed = base64_decode(Crypt::decryptString($encryptedSeed), true);
        $this->assertIsString($seed);
        $this->assertSame(32, strlen($seed));

        $replay = $this->startAttempt($key)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($start->json(), $replay->json());
        $this->assertDatabaseCount('attempts', 1);

        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/v1/attempts', ['quiz_id' => '01J00000000000000000000099'])
            ->assertConflict()
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');
        $this->assertDatabaseCount('attempts', 1);

        $second = $this->startAttempt('start-attempt-command-0002')->assertCreated();
        $secondAttemptId = (string) $second->json('data.id');
        $secondOrder = DB::table('attempt_questions')
            ->where('attempt_id', $secondAttemptId)
            ->orderBy('position')
            ->pluck('question_id')
            ->all();
        $this->assertNotSame($sourceOrder, $secondOrder);
        $this->assertNotSame(
            DB::table('attempts')->where('id', $attemptId)->value('seed_encrypted'),
            DB::table('attempts')->where('id', $secondAttemptId)->value('seed_encrypted'),
        );

        $resume = $this->withToken(self::TOKEN)->getJson('/v1/attempts/'.$attemptId)->assertOk();
        $this->assertSame(
            $start->json('data.questions'),
            $resume->json('data.questions'),
        );

        $storedKey = DB::table('idempotency_keys')->where('operation', 'attempt.start')->first();
        $this->assertNotNull($storedKey);
        $this->assertNotSame($key, $storedKey->key_digest);
    }

    public function test_answers_submit_progress_and_outbox_are_persistent_and_replay_safe(): void
    {
        $start = $this->startAttempt('start-graded-attempt-001')->assertCreated();
        $attemptId = (string) $start->json('data.id');
        /** @var list<array<string, mixed>> $questions */
        $questions = $start->json('data.questions');

        foreach ($questions as $index => $question) {
            /** @var array<string, mixed> $contract */
            $contract = $question['response_contract'];
            $value = $contract['kind'] === 'single_choice'
                ? $contract['options'][0]['id']
                : 'review';
            $this->withToken(self::TOKEN)
                ->withHeader('Idempotency-Key', 'answer-command-'.str_pad((string) $index, 8, '0', STR_PAD_LEFT))
                ->putJson('/v1/attempts/'.$attemptId.'/answers/'.$question['attempt_question_id'], [
                    'expected_revision' => 0,
                    'value' => $value,
                ])
                ->assertOk()
                ->assertJsonPath('data.revision', 1);
        }

        $firstQuestionId = (string) $questions[0]['attempt_question_id'];
        /** @var array<string, mixed> $firstContract */
        $firstContract = $questions[0]['response_contract'];
        $firstValue = $firstContract['kind'] === 'single_choice'
            ? $firstContract['options'][0]['id']
            : 'review';
        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'answer-conflict-command-001')
            ->putJson('/v1/attempts/'.$attemptId.'/answers/'.$firstQuestionId, [
                'expected_revision' => 0,
                'value' => $firstValue,
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'ANSWER_REVISION_CONFLICT');

        $submitKey = 'submit-attempt-command-001';
        $submitted = $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $submitKey)
            ->postJson('/v1/attempts/'.$attemptId.'/submit')
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.attempt.status', 'graded')
            ->assertJsonPath('data.score', 3)
            ->assertJsonPath('data.max_score', 3);

        $replay = $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $submitKey)
            ->postJson('/v1/attempts/'.$attemptId.'/submit')
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($submitted->json(), $replay->json());

        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'submit-attempt-command-002')
            ->postJson('/v1/attempts/'.$attemptId.'/submit')
            ->assertConflict()
            ->assertJsonPath('code', 'ATTEMPT_ALREADY_SUBMITTED');

        $this->withToken(self::TOKEN)->getJson('/v1/progress')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.curriculum_node_id', LearningSliceSeeder::TOPIC_NODE_ID)
            ->assertJsonPath('data.0.mastery', 1);

        $this->assertDatabaseHas('attempts', ['id' => $attemptId, 'status' => 'graded']);
        $this->assertDatabaseCount('attempt_answers', 3);
        $this->assertDatabaseHas('outbox_events', ['aggregate_id' => $attemptId, 'event_type' => 'assessment.attempt_started']);
        $this->assertDatabaseHas('outbox_events', ['aggregate_id' => $attemptId, 'event_type' => 'assessment.attempt_submitted']);
        $outboxPayloads = DB::table('outbox_events')->pluck('payload')->implode('\n');
        $this->assertStringNotContainsString('review', $outboxPayloads);
        $this->assertStringNotContainsString('correct_option_id', $outboxPayloads);
    }

    /** @return TestResponse<Response> */
    private function startAttempt(string $idempotencyKey): TestResponse
    {
        return $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/v1/attempts', ['quiz_id' => '01J00000000000000000000020']);
    }

    /** @param TestResponse<Response> $response */
    private function responseContent(TestResponse $response): string
    {
        $content = $response->getContent();
        if (! is_string($content)) {
            $this->fail('Expected a buffered response body.');
        }

        return $content;
    }
}

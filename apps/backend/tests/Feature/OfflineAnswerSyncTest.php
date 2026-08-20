<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AttemptService;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class OfflineAnswerSyncTest extends TestCase
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

    public function test_answer_sync_is_authenticated_bounded_and_returns_acknowledgements_in_input_order(): void
    {
        $this->postJson('/v1/sync/answers', ['operations' => []])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'AUTHENTICATION_REQUIRED');

        $this->withToken(self::TOKEN)->postJson('/v1/sync/answers', ['operations' => []])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'SYNC_BATCH_SIZE_INVALID');

        $tooMany = array_fill(0, 101, []);
        $this->withToken(self::TOKEN)->postJson('/v1/sync/answers', ['operations' => $tooMany])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'SYNC_BATCH_SIZE_INVALID');
        $this->assertDatabaseCount('answer_sync_acknowledgements', 0);

        $start = $this->startAttempt('sync-bounds-start-0001')->assertCreated();
        $attemptId = (string) $start->json('data.id');
        /** @var list<array<string, mixed>> $questions */
        $questions = $start->json('data.questions');
        $operation = $this->operation('sync-max-operation-0001', $attemptId, $questions[0]);
        $maximumBatch = array_fill(0, 100, $operation);

        $response = $this->sync($maximumBatch)
            ->assertOk()
            ->assertJsonCount(100, 'data.acknowledgements');

        $this->assertSame('sync-max-operation-0001', $response->json('data.acknowledgements.0.operation_id'));
        $this->assertSame('applied', $response->json('data.acknowledgements.0.outcome'));
        $this->assertFalse((bool) $response->json('data.acknowledgements.0.replayed'));
        $this->assertSame('sync-max-operation-0001', $response->json('data.acknowledgements.99.operation_id'));
        $this->assertTrue((bool) $response->json('data.acknowledgements.99.replayed'));
        $this->assertDatabaseCount('attempt_answers', 1);
        $this->assertDatabaseCount('answer_sync_acknowledgements', 1);
    }

    public function test_interrupted_batch_resumes_by_operation_id_without_duplicate_answer_or_outbox_write(): void
    {
        $start = $this->startAttempt('sync-resume-start-0001')->assertCreated();
        $attemptId = (string) $start->json('data.id');
        /** @var list<array<string, mixed>> $questions */
        $questions = $start->json('data.questions');

        $first = $this->operation('sync-resume-operation-0001', $attemptId, $questions[0]);
        $second = $this->operation('sync-resume-operation-0002', $attemptId, $questions[1]);

        $initial = $this->sync([$first])
            ->assertOk()
            ->assertJsonPath('data.acknowledgements.0.outcome', 'applied')
            ->assertJsonPath('data.acknowledgements.0.code', 'SYNC_ANSWER_APPLIED')
            ->assertJsonPath('data.acknowledgements.0.replayed', false)
            ->assertJsonPath('data.acknowledgements.0.answer_revision', 1);
        $firstAnsweredAt = $initial->json('data.acknowledgements.0.answered_at');
        $this->assertIsString($firstAnsweredAt);

        $answerEventsBeforeResume = DB::table('outbox_events')
            ->where('aggregate_id', $attemptId)
            ->where('event_type', 'assessment.answer_recorded')
            ->count();

        $resumed = $this->sync([$first, $second])
            ->assertOk()
            ->assertJsonCount(2, 'data.acknowledgements')
            ->assertJsonPath('data.acknowledgements.0.operation_id', 'sync-resume-operation-0001')
            ->assertJsonPath('data.acknowledgements.0.outcome', 'applied')
            ->assertJsonPath('data.acknowledgements.0.replayed', true)
            ->assertJsonPath('data.acknowledgements.0.answer_revision', 1)
            ->assertJsonPath('data.acknowledgements.1.operation_id', 'sync-resume-operation-0002')
            ->assertJsonPath('data.acknowledgements.1.outcome', 'applied')
            ->assertJsonPath('data.acknowledgements.1.replayed', false)
            ->assertJsonPath('data.acknowledgements.1.answer_revision', 1);

        $this->assertSame($firstAnsweredAt, $resumed->json('data.acknowledgements.0.answered_at'));
        $this->assertDatabaseCount('attempt_answers', 2);
        $this->assertDatabaseCount('answer_sync_acknowledgements', 2);
        $this->assertSame(
            $answerEventsBeforeResume + 1,
            DB::table('outbox_events')
                ->where('aggregate_id', $attemptId)
                ->where('event_type', 'assessment.answer_recorded')
                ->count(),
        );

        $stored = DB::table('answer_sync_acknowledgements')->orderBy('created_at')->first();
        $this->assertNotNull($stored);
        $this->assertNotSame('sync-resume-operation-0001', $stored->operation_id_digest);
        $this->assertSame(64, strlen((string) $stored->operation_id_digest));
        $this->assertSame(64, strlen((string) $stored->request_hash));

        $outboxPayloads = DB::table('outbox_events')
            ->where('event_type', 'assessment.answer_recorded')
            ->pluck('payload')
            ->implode('\n');
        $this->assertStringNotContainsString('review', $outboxPayloads);
        $this->assertStringNotContainsString('value', $outboxPayloads);
    }

    public function test_reusing_operation_id_with_changed_payload_conflicts_without_replacing_the_original_acknowledgement(): void
    {
        $start = $this->startAttempt('sync-reuse-start-0001')->assertCreated();
        $attemptId = (string) $start->json('data.id');
        /** @var list<array<string, mixed>> $questions */
        $questions = $start->json('data.questions');

        $original = $this->operation('sync-reuse-operation-0001', $attemptId, $questions[0]);
        $this->sync([$original])
            ->assertOk()
            ->assertJsonPath('data.acknowledgements.0.outcome', 'applied')
            ->assertJsonPath('data.acknowledgements.0.answer_revision', 1);

        $storedHash = DB::table('answer_sync_acknowledgements')->value('request_hash');
        $answerEvents = DB::table('outbox_events')
            ->where('aggregate_id', $attemptId)
            ->where('event_type', 'assessment.answer_recorded')
            ->count();

        $changed = $original;
        $changed['expected_revision'] = 1;
        $changed['value'] = $this->answerValue($questions[0], true);

        $conflict = $this->sync([$changed])
            ->assertOk()
            ->assertJsonPath('data.acknowledgements.0.operation_id', 'sync-reuse-operation-0001')
            ->assertJsonPath('data.acknowledgements.0.outcome', 'conflict')
            ->assertJsonPath('data.acknowledgements.0.code', 'SYNC_OPERATION_ID_REUSED')
            ->assertJsonPath('data.acknowledgements.0.replayed', false)
            ->assertJsonPath('data.acknowledgements.0.retryable', false)
            ->assertJsonPath('data.acknowledgements.0.answer_revision', null)
            ->assertJsonPath('data.acknowledgements.0.answered_at', null);
        $this->assertStringNotContainsString('detail', $this->responseContent($conflict));

        $this->assertDatabaseCount('attempt_answers', 1);
        $this->assertDatabaseCount('answer_sync_acknowledgements', 1);
        $this->assertSame($storedHash, DB::table('answer_sync_acknowledgements')->value('request_hash'));
        $this->assertSame(
            $answerEvents,
            DB::table('outbox_events')
                ->where('aggregate_id', $attemptId)
                ->where('event_type', 'assessment.answer_recorded')
                ->count(),
        );

        $this->sync([$original])
            ->assertOk()
            ->assertJsonPath('data.acknowledgements.0.outcome', 'applied')
            ->assertJsonPath('data.acknowledgements.0.code', 'SYNC_ANSWER_APPLIED')
            ->assertJsonPath('data.acknowledgements.0.replayed', true)
            ->assertJsonPath('data.acknowledgements.0.answer_revision', 1);
        $this->assertDatabaseCount('attempt_answers', 1);
    }

    public function test_revision_and_resource_conflicts_are_durable_isolated_and_cross_user_safe(): void
    {
        $start = $this->startAttempt('sync-isolation-start-0001')->assertCreated();
        $attemptId = (string) $start->json('data.id');
        /** @var list<array<string, mixed>> $questions */
        $questions = $start->json('data.questions');

        $first = $this->operation('sync-isolation-prime-0001', $attemptId, $questions[0]);
        $this->sync([$first])->assertOk()->assertJsonPath('data.acknowledgements.0.outcome', 'applied');

        $otherUser = User::factory()->create();
        $otherContextId = (string) Str::ulid();
        $now = now();
        DB::table('user_academic_contexts')->insert([
            'id' => $otherContextId,
            'user_id' => $otherUser->getKey(),
            'academic_track_id' => LearningSliceSeeder::TRACK_ID,
            'status' => 'active',
            'activated_at' => $now,
            'archived_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $otherAttempt = app(AttemptService::class)->start($otherUser, LearningSliceSeeder::QUIZ_ID);
        /** @var list<array<string, mixed>> $otherQuestions */
        $otherQuestions = $otherAttempt['questions'];

        $stale = $this->operation('sync-isolation-stale-0001', $attemptId, $questions[0]);
        $valid = $this->operation('sync-isolation-valid-0001', $attemptId, $questions[1]);
        $foreign = $this->operation('sync-isolation-foreign-0001', (string) $otherAttempt['id'], $otherQuestions[0]);

        $mixed = $this->sync([$stale, $valid, $foreign])
            ->assertOk()
            ->assertJsonCount(3, 'data.acknowledgements')
            ->assertJsonPath('data.acknowledgements.0.operation_id', 'sync-isolation-stale-0001')
            ->assertJsonPath('data.acknowledgements.0.outcome', 'conflict')
            ->assertJsonPath('data.acknowledgements.0.code', 'ANSWER_REVISION_CONFLICT')
            ->assertJsonPath('data.acknowledgements.1.operation_id', 'sync-isolation-valid-0001')
            ->assertJsonPath('data.acknowledgements.1.outcome', 'applied')
            ->assertJsonPath('data.acknowledgements.1.code', 'SYNC_ANSWER_APPLIED')
            ->assertJsonPath('data.acknowledgements.2.operation_id', 'sync-isolation-foreign-0001')
            ->assertJsonPath('data.acknowledgements.2.outcome', 'rejected')
            ->assertJsonPath('data.acknowledgements.2.code', 'RESOURCE_NOT_FOUND');

        $this->assertStringNotContainsString('detail', $this->responseContent($mixed));
        $this->assertDatabaseCount('attempt_answers', 2);
        $this->assertDatabaseCount('answer_sync_acknowledgements', 4);
        $this->assertDatabaseHas('answer_sync_acknowledgements', ['code' => 'ANSWER_REVISION_CONFLICT', 'outcome' => 'conflict']);
        $this->assertDatabaseHas('answer_sync_acknowledgements', ['code' => 'RESOURCE_NOT_FOUND', 'outcome' => 'rejected']);

        $replayedFailure = $this->sync([$stale])
            ->assertOk()
            ->assertJsonPath('data.acknowledgements.0.code', 'ANSWER_REVISION_CONFLICT')
            ->assertJsonPath('data.acknowledgements.0.replayed', true);
        $this->assertStringNotContainsString('detail', $this->responseContent($replayedFailure));
        $this->assertDatabaseCount('attempt_answers', 2);
    }

    /** @return TestResponse<Response> */
    private function startAttempt(string $idempotencyKey): TestResponse
    {
        return $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/v1/attempts', ['quiz_id' => LearningSliceSeeder::QUIZ_ID]);
    }

    /**
     * @param  list<array<string, mixed>>  $operations
     * @return TestResponse<Response>
     */
    private function sync(array $operations): TestResponse
    {
        return $this->withToken(self::TOKEN)->postJson('/v1/sync/answers', ['operations' => $operations]);
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array{operation_id: string, attempt_id: string, attempt_question_id: string, expected_revision: int, value: mixed}
     */
    private function operation(string $operationId, string $attemptId, array $question): array
    {
        return [
            'operation_id' => $operationId,
            'attempt_id' => $attemptId,
            'attempt_question_id' => (string) $question['attempt_question_id'],
            'expected_revision' => 0,
            'value' => $this->answerValue($question),
        ];
    }

    /** @param array<string, mixed> $question */
    private function answerValue(array $question, bool $alternate = false): mixed
    {
        /** @var array<string, mixed> $contract */
        $contract = $question['response_contract'];
        if ($contract['kind'] === 'single_choice') {
            /** @var list<array{id: string}> $options */
            $options = $contract['options'];

            return $options[$alternate ? count($options) - 1 : 0]['id'];
        }

        return $alternate ? 'changed-review' : 'review';
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

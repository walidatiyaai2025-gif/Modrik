<?php

namespace Tests\Feature;

use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AcademicContextLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'modrik-local-fixture-token';

    private const TARGET_TRACK_ID = '01J00000000000000000000040';

    private const TARGET_TRACK_AUTHORIZATION_ID = '01J00000000000000000000041';

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
        $this->createTargetTrack();
    }

    public function test_onboarding_activation_is_idempotent_and_cannot_replace_an_active_context(): void
    {
        DB::table('user_academic_contexts')
            ->where('id', LearningSliceSeeder::CONTEXT_ID)
            ->update(['status' => 'archived', 'archived_at' => now(), 'updated_at' => now()]);

        $key = 'academic-activate-command-0001';
        $activated = $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/v1/academic-context/activate', ['academic_track_id' => LearningSliceSeeder::TRACK_ID])
            ->assertCreated()
            ->assertHeader('Location', '/v1/academic-context')
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.state', 'active')
            ->assertJsonPath('data.academic_track_id', LearningSliceSeeder::TRACK_ID);

        $contextId = (string) $activated->json('data.context_id');
        $this->assertNotSame(LearningSliceSeeder::CONTEXT_ID, $contextId);
        $this->assertDatabaseHas('academic_context_transitions', [
            'from_context_id' => null,
            'to_context_id' => $contextId,
            'action' => 'activated',
        ]);
        $this->assertDatabaseHas('outbox_events', [
            'aggregate_id' => $contextId,
            'event_type' => 'academic.context_activated',
        ]);

        $replay = $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/v1/academic-context/activate', ['academic_track_id' => LearningSliceSeeder::TRACK_ID])
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($activated->json(), $replay->json());
        $this->assertSame(1, DB::table('academic_context_transitions')->count());

        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/v1/academic-context/activate', ['academic_track_id' => self::TARGET_TRACK_ID])
            ->assertConflict()
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');

        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'academic-activate-command-0002')
            ->postJson('/v1/academic-context/activate', ['academic_track_id' => self::TARGET_TRACK_ID])
            ->assertConflict()
            ->assertJsonPath('code', 'ACADEMIC_CONTEXT_RESET_REQUIRED');
    }

    public function test_full_reset_archives_attempts_and_progress_without_deleting_history(): void
    {
        $gradedAttempt = $this->completePractice();
        $inProgressAttempt = $this->startPractice('academic-reset-in-progress-start-0001')
            ->assertCreated();
        $gradedAttemptId = (string) $gradedAttempt->json('data.attempt.id');
        $inProgressAttemptId = (string) $inProgressAttempt->json('data.id');
        $this->assertSame(LearningSliceSeeder::CONTEXT_ID, $gradedAttempt->json('data.attempt.academic_context_id'));
        $this->assertDatabaseCount('progress_snapshots', 1);

        $key = 'academic-reset-command-0001';
        $reset = $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/v1/academic-context/reset', ['academic_track_id' => self::TARGET_TRACK_ID])
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.state', 'active')
            ->assertJsonPath('data.academic_track_id', self::TARGET_TRACK_ID)
            ->assertJsonPath('data.year_level', 'FIXTURE-YEAR-8');

        $newContextId = (string) $reset->json('data.context_id');
        $this->assertNotSame(LearningSliceSeeder::CONTEXT_ID, $newContextId);
        $this->assertDatabaseHas('user_academic_contexts', [
            'id' => LearningSliceSeeder::CONTEXT_ID,
            'status' => 'archived',
        ]);
        $this->assertNotNull(DB::table('user_academic_contexts')->where('id', LearningSliceSeeder::CONTEXT_ID)->value('archived_at'));

        $this->assertSame(2, DB::table('attempts')->where('academic_context_id', LearningSliceSeeder::CONTEXT_ID)->count());
        $this->assertSame(2, DB::table('attempts')->where('academic_context_id', LearningSliceSeeder::CONTEXT_ID)->whereNotNull('archived_at')->count());
        $this->assertDatabaseHas('attempts', ['id' => $gradedAttemptId, 'status' => 'graded']);
        $this->assertDatabaseHas('attempts', ['id' => $inProgressAttemptId, 'status' => 'abandoned']);
        $this->assertNotNull(DB::table('attempts')->where('id', $inProgressAttemptId)->value('completed_at'));
        $this->assertDatabaseCount('progress_snapshots', 1);
        $this->assertNotNull(DB::table('progress_snapshots')->value('archived_at'));

        $this->withToken(self::TOKEN)->getJson('/v1/progress')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->withToken(self::TOKEN)->getJson('/v1/attempts/'.$gradedAttemptId)
            ->assertOk()
            ->assertJsonPath('data.academic_context_id', LearningSliceSeeder::CONTEXT_ID)
            ->assertJsonPath('data.status', 'graded')
            ->assertJsonPath('data.archived_at', fn (mixed $value): bool => is_string($value) && $value !== '');
        $this->startPractice('academic-reset-old-track-start-0001')
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

        $this->assertDatabaseHas('academic_context_transitions', [
            'from_context_id' => LearningSliceSeeder::CONTEXT_ID,
            'to_context_id' => $newContextId,
            'action' => 'reset',
            'archived_attempt_count' => 2,
            'archived_progress_count' => 1,
        ]);
        $eventPayload = DB::table('outbox_events')->where('event_type', 'academic.context_reset')->value('payload');
        $this->assertIsString($eventPayload);
        $decodedPayload = json_decode($eventPayload, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(2, $decodedPayload['archived_attempt_count']);
        $this->assertSame(1, $decodedPayload['archived_progress_count']);
        $this->assertArrayNotHasKey('user_id', $decodedPayload);

        $replay = $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/v1/academic-context/reset', ['academic_track_id' => self::TARGET_TRACK_ID])
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($reset->json(), $replay->json());
        $this->assertSame(1, DB::table('academic_context_transitions')->where('action', 'reset')->count());

        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/v1/academic-context/reset', ['academic_track_id' => LearningSliceSeeder::TRACK_ID])
            ->assertConflict()
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');
        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'academic-reset-command-0002')
            ->postJson('/v1/academic-context/reset', ['academic_track_id' => self::TARGET_TRACK_ID])
            ->assertConflict()
            ->assertJsonPath('code', 'ACADEMIC_CONTEXT_UNCHANGED');
    }

    private function createTargetTrack(): void
    {
        $now = now();
        DB::table('academic_tracks')->insert([
            'id' => self::TARGET_TRACK_ID,
            'code' => 'FIXTURE:SECONDARY:8',
            'year_level' => 'FIXTURE-YEAR-8',
            'title' => json_encode([
                'ar' => 'مسار تركيبي ثانٍ',
                'en' => 'Synthetic second track',
                'fr' => 'Deuxième parcours synthétique',
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'is_fixture' => true,
            'availability_state' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('academic_track_authorizations')->insert([
            'id' => self::TARGET_TRACK_AUTHORIZATION_ID,
            'user_id' => LearningSliceSeeder::USER_ID,
            'academic_track_id' => self::TARGET_TRACK_ID,
            'sort_order' => 200,
            'authorized_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return TestResponse<Response> */
    private function completePractice(): TestResponse
    {
        $attempt = $this->startPractice('academic-reset-graded-start-0001')->assertCreated();
        $attemptId = (string) $attempt->json('data.id');
        foreach ($attempt->json('data.questions') as $index => $question) {
            $contract = $question['response_contract'];
            $value = $contract['kind'] === 'single_choice' ? $contract['options'][0]['id'] : 'review';
            $this->withToken(self::TOKEN)
                ->withHeader('Idempotency-Key', 'academic-reset-answer-'.$index.'-0001')
                ->putJson('/v1/attempts/'.$attemptId.'/answers/'.$question['attempt_question_id'], [
                    'expected_revision' => 0,
                    'value' => $value,
                ])
                ->assertOk();
        }

        return $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'academic-reset-submit-command-0001')
            ->postJson('/v1/attempts/'.$attemptId.'/submit')
            ->assertOk();
    }

    /** @return TestResponse<Response> */
    private function startPractice(string $key): TestResponse
    {
        return $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/v1/attempts', ['quiz_id' => LearningSliceSeeder::QUIZ_ID]);
    }
}

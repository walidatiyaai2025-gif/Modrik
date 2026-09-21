<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AttemptService;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class CurrentAttemptRecoveryTest extends TestCase
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

    public function test_current_attempt_is_backend_authoritative_and_tracks_only_active_context(): void
    {
        $this->withToken(self::TOKEN)
            ->getJson('/v1/attempts/current')
            ->assertOk()
            ->assertJsonPath('data.attempt', null)
            ->assertHeader('Cache-Control', 'no-store, private');

        $first = $this->start('current-attempt-start-0001');
        $firstId = (string) $first->json('data.id');

        $second = $this->start('current-attempt-start-0002');
        $secondId = (string) $second->json('data.id');

        $this->withToken(self::TOKEN)
            ->getJson('/v1/attempts/current')
            ->assertOk()
            ->assertJsonPath('data.attempt.id', $secondId)
            ->assertJsonPath('data.attempt.status', 'in_progress');

        $foreign = User::factory()->create();
        self::assertNull(app(AttemptService::class)->current($foreign));

        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'current-attempt-submit-0002')
            ->postJson('/v1/attempts/'.$secondId.'/submit', [])
            ->assertOk();

        $this->withToken(self::TOKEN)
            ->getJson('/v1/attempts/current')
            ->assertOk()
            ->assertJsonPath('data.attempt.id', $firstId);

        DB::table('user_academic_contexts')
            ->where('id', LearningSliceSeeder::CONTEXT_ID)
            ->update([
                'status' => 'archived',
                'archived_at' => now(),
                'updated_at' => now(),
            ]);

        $this->withToken(self::TOKEN)
            ->getJson('/v1/attempts/current')
            ->assertOk()
            ->assertJsonPath('data.attempt', null);
    }

    private function start(string $key): TestResponse
    {
        return $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/v1/attempts', ['quiz_id' => LearningSliceSeeder::QUIZ_ID])
            ->assertCreated();
    }
}

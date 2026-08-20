<?php

namespace Tests\Feature;

use App\Services\OptionalAiBoundary;
use Database\Seeders\LearningSliceSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaidAiBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'modrik-local-fixture-token';

    private const LESSON_ID = '01J00000000000000000000003';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'modrik.fixture.enabled' => true,
            'modrik.fixture.bearer_token' => self::TOKEN,
            'modrik.fixture.user_id' => LearningSliceSeeder::USER_ID,
            'modrik.idempotency.secret' => 'test-only-idempotency-secret',
            'modrik.paid_ai.enabled' => false,
        ]);
        $this->seed(LearningSliceSeeder::class);
    }

    public function test_complete_learning_core_works_with_paid_ai_off_and_no_outbound_http(): void
    {
        Http::preventStrayRequests();

        $this->assertFalse((bool) config('modrik.paid_ai.enabled'));
        $this->withToken(self::TOKEN)->getJson('/v1/session')->assertOk();
        $this->withToken(self::TOKEN)->getJson('/v1/academic-context')->assertOk();
        $this->withToken(self::TOKEN)
            ->getJson('/v1/lessons/'.self::LESSON_ID)
            ->assertOk()
            ->assertJsonPath('data.practice_quiz_id', LearningSliceSeeder::QUIZ_ID);

        $attempt = $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'paid-ai-off-attempt-0001')
            ->postJson('/v1/attempts', ['quiz_id' => LearningSliceSeeder::QUIZ_ID])
            ->assertCreated();
        $attemptId = (string) $attempt->json('data.id');
        /** @var list<array<string, mixed>> $questions */
        $questions = $attempt->json('data.questions');

        foreach ($questions as $index => $question) {
            /** @var array<string, mixed> $contract */
            $contract = $question['response_contract'];
            $value = $contract['kind'] === 'single_choice'
                ? $contract['options'][0]['id']
                : 'review';
            $this->withToken(self::TOKEN)
                ->withHeader('Idempotency-Key', 'paid-ai-off-answer-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT))
                ->putJson('/v1/attempts/'.$attemptId.'/answers/'.$question['attempt_question_id'], [
                    'expected_revision' => 0,
                    'value' => $value,
                ])
                ->assertOk();
        }

        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'paid-ai-off-submit-0001')
            ->postJson('/v1/attempts/'.$attemptId.'/submit')
            ->assertOk()
            ->assertJsonPath('data.attempt.status', 'graded');
        $this->withToken(self::TOKEN)
            ->getJson('/v1/progress')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertDatabaseHas('attempts', ['id' => $attemptId, 'status' => 'graded']);
        $this->assertSame(count($questions), DB::table('attempt_answers')->count());
        Http::assertNothingSent();
    }

    public function test_optional_ai_boundary_refuses_context_while_disabled(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('PAID_AI_DISABLED');

        $this->app->make(OptionalAiBoundary::class)->prepareContext([
            'locale' => 'en',
            'student_answer' => 'must never leave the core',
        ]);
    }

    public function test_optional_ai_boundary_allowlists_non_pii_context_when_explicitly_enabled(): void
    {
        Http::preventStrayRequests();
        config(['modrik.paid_ai.enabled' => true]);

        $prepared = $this->app->make(OptionalAiBoundary::class)->prepareContext([
            'locale' => 'ar',
            'subject_reference' => 'synthetic-subject',
            'lesson_reference' => 'synthetic-lesson',
            'user_id' => LearningSliceSeeder::USER_ID,
            'email' => 'student@example.invalid',
            'student_answer' => 'private answer',
            'access_token' => 'secret',
        ]);

        $this->assertSame([
            'locale' => 'ar',
            'subject_reference' => 'synthetic-subject',
            'lesson_reference' => 'synthetic-lesson',
        ], $prepared);
        Http::assertNothingSent();
    }
}

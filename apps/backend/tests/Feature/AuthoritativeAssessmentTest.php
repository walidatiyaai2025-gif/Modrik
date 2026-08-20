<?php

namespace Tests\Feature;

use App\Services\AssessmentEngine;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AuthoritativeAssessmentTest extends TestCase
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

    public function test_client_cannot_control_authority_and_fresh_attempts_do_not_repeat_static_order(): void
    {
        foreach (['seed', 'question_ids', 'order', 'selection', 'blueprint'] as $index => $field) {
            $this->withToken(self::TOKEN)
                ->withHeader('Idempotency-Key', 'assessment-abuse-'.str_pad((string) $index, 8, '0', STR_PAD_LEFT))
                ->postJson('/v1/attempts', [
                    'quiz_id' => LearningSliceSeeder::QUIZ_ID,
                    $field => $field === 'seed' ? 'client-seed' : [],
                ])
                ->assertUnprocessable()
                ->assertJsonPath('code', 'VALIDATION_FAILED')
                ->assertJsonPath('errors.0.code', 'FIELD_NOT_ALLOWED');
        }
        $this->assertDatabaseCount('attempts', 0);

        $sourceOrder = DB::table('quiz_questions')
            ->where('quiz_id', LearningSliceSeeder::QUIZ_ID)
            ->orderBy('source_position')
            ->pluck('question_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        $previousOrder = [];
        $seeds = [];
        for ($index = 0; $index < 12; $index++) {
            $start = $this->startAttempt(LearningSliceSeeder::QUIZ_ID, 'assessment-property-'.str_pad((string) $index, 8, '0', STR_PAD_LEFT))
                ->assertCreated()
                ->assertJsonPath('data.ordering_algorithm', AssessmentEngine::ALGORITHM);
            $attemptId = (string) $start->json('data.id');
            $order = DB::table('attempt_questions')
                ->where('attempt_id', $attemptId)
                ->orderBy('position')
                ->pluck('question_id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all();
            $this->assertNotSame($sourceOrder, $order, 'A multi-question attempt must not use static canonical order.');
            if ($previousOrder !== []) {
                $this->assertNotSame($previousOrder, $order, 'Consecutive attempts must not repeat the prior authoritative order.');
            }
            $previousOrder = $order;

            $encrypted = DB::table('attempts')->where('id', $attemptId)->value('seed_encrypted');
            $this->assertIsString($encrypted);
            $seed = base64_decode(Crypt::decryptString($encrypted), true);
            $this->assertIsString($seed);
            $this->assertSame(32, strlen($seed));
            $this->assertNotContains(bin2hex($seed), $seeds);
            $seeds[] = bin2hex($seed);
            $this->assertSame(hash('sha256', $seed), DB::table('attempts')->where('id', $attemptId)->value('seed_fingerprint'));

            $resume = $this->withToken(self::TOKEN)->getJson('/v1/attempts/'.$attemptId)->assertOk();
            $this->assertSame($start->json('data.questions'), $resume->json('data.questions'));
            $this->assertStringNotContainsString('seed_encrypted', $this->responseContent($resume));
            $this->assertStringNotContainsString('grading_contract', $this->responseContent($resume));
        }
    }

    public function test_blueprint_slot_selection_rotates_sets_while_preserving_scope_difficulty_marks_and_coverage(): void
    {
        $quizId = (string) Str::ulid();
        $questionIds = [];
        for ($index = 0; $index < 4; $index++) {
            $questionId = (string) Str::ulid();
            $questionIds[] = $questionId;
            $this->insertChoiceQuestion(
                $questionId,
                maximumScore: 2,
                metadata: ['section' => 'core', 'difficulty' => 'medium', 'concepts' => ['fractions']],
            );
        }
        $this->insertQuiz($quizId, $questionIds, [
            'question_order' => 'shuffle',
            'slots' => [[
                'section' => 'core',
                'difficulty' => 'medium',
                'marks' => 2,
                'coverage' => ['fractions'],
                'count' => 2,
            ]],
        ], 7);

        $previousSet = [];
        for ($index = 0; $index < 8; $index++) {
            $start = $this->startAttempt($quizId, 'blueprint-rotation-'.str_pad((string) $index, 8, '0', STR_PAD_LEFT))->assertCreated();
            $attemptId = (string) $start->json('data.id');
            $selected = DB::table('attempt_questions')
                ->where('attempt_id', $attemptId)
                ->pluck('question_id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all();
            $this->assertCount(2, $selected);
            $sorted = $selected;
            sort($sorted);
            if ($previousSet !== []) {
                $this->assertNotSame($previousSet, $sorted, 'A variable blueprint must rotate the selected set on consecutive attempts.');
            }
            $previousSet = $sorted;

            foreach ($selected as $questionId) {
                $question = DB::table('questions')->where('id', $questionId)->first(['curriculum_node_id', 'maximum_score', 'assessment_metadata']);
                $this->assertNotNull($question);
                $this->assertSame(LearningSliceSeeder::TOPIC_NODE_ID, $question->curriculum_node_id);
                $this->assertSame(2.0, (float) $question->maximum_score);
                $metadata = json_decode((string) $question->assessment_metadata, true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame('core', $metadata['section']);
                $this->assertSame('medium', $metadata['difficulty']);
                $this->assertContains('fractions', $metadata['concepts']);
            }
        }
    }

    public function test_resume_and_scoring_use_immutable_snapshot_after_question_bank_and_quiz_mutation(): void
    {
        $questionId = (string) Str::ulid();
        $quizId = (string) Str::ulid();
        $this->insertChoiceQuestion($questionId, maximumScore: 5, correctOptionId: 'A');
        $this->insertQuiz($quizId, [$questionId], null, 7);

        $start = $this->startAttempt($quizId, 'immutable-snapshot-start-0001')->assertCreated();
        $attemptId = (string) $start->json('data.id');
        $attemptQuestionId = (string) $start->json('data.questions.0.attempt_question_id');
        $originalPrompt = $start->json('data.questions.0.prompt');

        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'immutable-snapshot-answer-001')
            ->putJson('/v1/attempts/'.$attemptId.'/answers/'.$attemptQuestionId, [
                'expected_revision' => 0,
                'value' => 'A',
            ])
            ->assertOk();

        DB::table('questions')->where('id', $questionId)->update([
            'prompt' => $this->json(['en' => 'MUTATED', 'ar' => 'MUTATED', 'fr' => 'MUTATED']),
            'options' => $this->json([
                ['id' => 'A', 'label' => ['en' => 'Wrong now', 'ar' => 'Wrong now', 'fr' => 'Wrong now']],
                ['id' => 'B', 'label' => ['en' => 'Correct now', 'ar' => 'Correct now', 'fr' => 'Correct now']],
            ]),
            'answer_contract' => $this->json(['correct_option_id' => 'B']),
            'maximum_score' => 99,
            'status' => 'retired',
        ]);
        DB::table('quizzes')->where('id', $quizId)->update([
            'blueprint_version' => 99,
            'blueprint' => $this->json(['question_order' => 'fixed']),
        ]);

        $resume = $this->withToken(self::TOKEN)->getJson('/v1/attempts/'.$attemptId)->assertOk();
        $this->assertSame($originalPrompt, $resume->json('data.questions.0.prompt'));

        $submitted = $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'immutable-snapshot-submit-001')
            ->postJson('/v1/attempts/'.$attemptId.'/submit')
            ->assertOk()
            ->assertJsonPath('data.score', 5)
            ->assertJsonPath('data.max_score', 5)
            ->assertJsonPath('data.attempt.blueprint_version', 7);

        $this->assertSame($originalPrompt, $submitted->json('data.attempt.questions.0.prompt'));
        $this->assertDatabaseHas('progress_snapshots', [
            'academic_context_id' => LearningSliceSeeder::CONTEXT_ID,
            'curriculum_node_id' => LearningSliceSeeder::TOPIC_NODE_ID,
            'source_version' => 7,
        ]);
    }

    public function test_option_shuffle_is_opt_in_and_unsafe_semantics_preserve_required_order(): void
    {
        $quizId = (string) Str::ulid();
        $safeId = (string) Str::ulid();
        $fixedId = (string) Str::ulid();
        $allNoneId = (string) Str::ulid();
        $sequenceId = (string) Str::ulid();
        $imageLetterId = (string) Str::ulid();

        $this->insertChoiceQuestion($safeId, optionShuffleSafe: true);
        $this->insertChoiceQuestion($fixedId, optionShuffleSafe: false);
        $this->insertChoiceQuestion($allNoneId, optionShuffleSafe: true, options: $this->optionsWithAllOfAbove());
        $this->insertChoiceQuestion($sequenceId, optionShuffleSafe: true, metadata: ['option_order_semantics' => 'sequence']);
        $this->insertChoiceQuestion($imageLetterId, optionShuffleSafe: true, metadata: ['option_order_semantics' => 'image_letter']);
        $questionIds = [$safeId, $fixedId, $allNoneId, $sequenceId, $imageLetterId];
        $this->insertQuiz($quizId, $questionIds, ['question_order' => 'fixed'], 4);

        $start = $this->startAttempt($quizId, 'option-safety-start-0001')->assertCreated();
        $attemptId = (string) $start->json('data.id');
        $this->assertSame($questionIds, DB::table('attempt_questions')->where('attempt_id', $attemptId)->orderBy('position')->pluck('question_id')->all());

        $sourceIds = ['A', 'B', 'C'];
        $this->assertNotSame($sourceIds, $this->snapshotOptionIds($attemptId, $safeId));
        $this->assertSame($sourceIds, $this->snapshotOptionIds($attemptId, $fixedId));
        $this->assertSame($sourceIds, $this->snapshotOptionIds($attemptId, $allNoneId));
        $this->assertSame($sourceIds, $this->snapshotOptionIds($attemptId, $sequenceId));
        $this->assertSame($sourceIds, $this->snapshotOptionIds($attemptId, $imageLetterId));

        $event = DB::table('outbox_events')->where('aggregate_id', $attemptId)->where('event_type', 'assessment.attempt_started')->value('payload');
        $this->assertIsString($event);
        $payload = json_decode($event, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('fixed', $payload['question_order_policy']);
        $this->assertSame(AssessmentEngine::ALGORITHM, $payload['ordering_algorithm']);
        $this->assertSame($questionIds, $payload['selected_question_ids']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $payload['seed_fingerprint']);
    }

    /** @return TestResponse<Response> */
    private function startAttempt(string $quizId, string $idempotencyKey): TestResponse
    {
        return $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/v1/attempts', ['quiz_id' => $quizId]);
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<array<string, mixed>>|null $options
     * @throws JsonException
     */
    private function insertChoiceQuestion(
        string $questionId,
        float $maximumScore = 1,
        string $correctOptionId = 'A',
        array $metadata = [],
        bool $optionShuffleSafe = false,
        ?array $options = null,
    ): void {
        $now = now();
        DB::table('questions')->insert([
            'id' => $questionId,
            'curriculum_node_id' => LearningSliceSeeder::TOPIC_NODE_ID,
            'content_version' => 1,
            'type' => 'single_choice',
            'prompt' => $this->json(['en' => 'Synthetic assessment question', 'ar' => 'سؤال تقييم اصطناعي', 'fr' => 'Question synthétique']),
            'options' => $this->json($options ?? $this->standardOptions()),
            'answer_contract' => $this->json(['correct_option_id' => $correctOptionId]),
            'explanation' => $this->json(['en' => 'Synthetic explanation', 'ar' => 'شرح اصطناعي', 'fr' => 'Explication synthétique']),
            'maximum_score' => $maximumScore,
            'assessment_metadata' => $metadata === [] ? null : $this->json($metadata),
            'option_shuffle_safe' => $optionShuffleSafe,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param list<string> $questionIds
     * @param array<string, mixed>|null $blueprint
     * @throws JsonException
     */
    private function insertQuiz(string $quizId, array $questionIds, ?array $blueprint, int $blueprintVersion): void
    {
        $now = now();
        DB::table('quizzes')->insert([
            'id' => $quizId,
            'curriculum_node_id' => LearningSliceSeeder::TOPIC_NODE_ID,
            'kind' => 'practice',
            'blueprint_version' => $blueprintVersion,
            'blueprint' => $blueprint === null ? null : $this->json($blueprint),
            'title' => $this->json(['en' => 'Synthetic assessment', 'ar' => 'تقييم اصطناعي', 'fr' => 'Évaluation synthétique']),
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        foreach ($questionIds as $index => $questionId) {
            DB::table('quiz_questions')->insert([
                'quiz_id' => $quizId,
                'question_id' => $questionId,
                'source_position' => $index + 1,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function standardOptions(): array
    {
        return [
            ['id' => 'A', 'label' => ['en' => 'Alpha', 'ar' => 'ألفا', 'fr' => 'Alpha']],
            ['id' => 'B', 'label' => ['en' => 'Beta', 'ar' => 'بيتا', 'fr' => 'Bêta']],
            ['id' => 'C', 'label' => ['en' => 'Gamma', 'ar' => 'جاما', 'fr' => 'Gamma']],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function optionsWithAllOfAbove(): array
    {
        $options = $this->standardOptions();
        $options[2]['label'] = ['en' => 'All of the above', 'ar' => 'كل ما سبق', 'fr' => 'Toutes les réponses'];

        return $options;
    }

    /** @return list<string> */
    private function snapshotOptionIds(string $attemptId, string $questionId): array
    {
        $snapshotJson = DB::table('attempt_questions')
            ->where('attempt_id', $attemptId)
            ->where('question_id', $questionId)
            ->value('question_snapshot');
        $this->assertIsString($snapshotJson);
        $snapshot = json_decode($snapshotJson, true, flags: JSON_THROW_ON_ERROR);
        $options = $snapshot['response_contract']['options'] ?? [];

        return array_values(array_map(static fn (array $option): string => (string) $option['id'], $options));
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

    /** @throws JsonException */
    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

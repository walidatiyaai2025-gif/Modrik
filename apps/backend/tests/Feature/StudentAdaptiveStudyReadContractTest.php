<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentAdaptiveStudyReadContractTest extends TestCase
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
        ]);
        $this->seed(LearningSliceSeeder::class);
    }

    public function test_adaptive_study_requires_auth_and_defaults_to_truthful_disabled_empty_state(): void
    {
        $this->getJson('/v1/adaptive-study')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'AUTHENTICATION_REQUIRED');

        $this->withToken(self::TOKEN)->getJson('/v1/adaptive-study')
            ->assertOk()
            ->assertJsonPath('data.state', 'active')
            ->assertJsonPath('data.context.context_id', LearningSliceSeeder::CONTEXT_ID)
            ->assertJsonPath('data.features.daily_plan.state', 'disabled')
            ->assertJsonPath('data.features.daily_plan.effective', false)
            ->assertJsonPath('data.today_mission.status', 'disabled')
            ->assertJsonPath('data.needs_practice.status', 'empty')
            ->assertJsonPath('data.mistakes.status', 'disabled');
    }

    public function test_enabled_adaptive_read_contract_returns_authoritative_ready_mission_and_mistakes_without_answer_leakage(): void
    {
        $this->enableFeature('daily_plan');
        $this->enableFeature('mistake_notebook');

        $evidence = $this->createSkillEvidence(
            code: 'FIXTURE:SKILL:RECOVERY',
            score: 0.25,
            withAssessment: true,
            withWrongAnswer: true,
        );

        $response = $this->withToken(self::TOKEN)->getJson('/v1/adaptive-study')
            ->assertOk()
            ->assertJsonPath('data.features.daily_plan.effective', true)
            ->assertJsonPath('data.features.mistake_notebook.effective', true)
            ->assertJsonPath('data.needs_practice.status', 'ready')
            ->assertJsonPath('data.needs_practice.items.0.skill_id', $evidence['skill_id'])
            ->assertJsonPath('data.needs_practice.items.0.display_band', 'critical')
            ->assertJsonPath('data.today_mission.status', 'ready')
            ->assertJsonPath('data.today_mission.selected.0.skill_id', $evidence['skill_id'])
            ->assertJsonPath('data.today_mission.selected.0.source_type', 'recent_mistake')
            ->assertJsonPath('data.today_mission.selected.0.assessment.id', $evidence['quiz_id'])
            ->assertJsonPath('data.mistakes.status', 'ready')
            ->assertJsonPath('data.mistakes.items.0.attempt_question_id', $evidence['attempt_question_id'])
            ->assertJsonPath('data.mistakes.items.0.skill_id', $evidence['skill_id'])
            ->assertJsonPath('data.mistakes.items.0.prompt.en', 'Which value needs recovery?');

        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringNotContainsString('DO_NOT_EXPOSE_CORRECT_ANSWER', $content);
        self::assertStringNotContainsString('DO_NOT_EXPOSE_EXPLANATION', $content);
        self::assertStringNotContainsString('grading_contract', $content);
    }

    public function test_daily_plan_reports_degraded_when_an_authoritative_weak_skill_has_no_published_practice_questions(): void
    {
        $this->enableFeature('daily_plan');
        $this->enableFeature('mistake_notebook');

        $available = $this->createSkillEvidence(
            code: 'FIXTURE:SKILL:AVAILABLE',
            score: 0.20,
            withAssessment: true,
            withWrongAnswer: true,
        );
        $unavailable = $this->createSkillEvidence(
            code: 'FIXTURE:SKILL:UNAVAILABLE',
            score: 0.50,
            withAssessment: false,
            withWrongAnswer: false,
        );

        $this->withToken(self::TOKEN)->getJson('/v1/adaptive-study')
            ->assertOk()
            ->assertJsonPath('data.today_mission.status', 'degraded')
            ->assertJsonPath('data.today_mission.selected.0.skill_id', $available['skill_id'])
            ->assertJsonPath('data.today_mission.skipped_unavailable.0.skill_id', $unavailable['skill_id'])
            ->assertJsonPath('data.today_mission.skipped_unavailable.0.reason', 'question_unavailable')
            ->assertJsonPath('data.needs_practice.items.0.skill_id', $available['skill_id'])
            ->assertJsonPath('data.needs_practice.items.1.skill_id', $unavailable['skill_id']);
    }

    public function test_enabled_features_return_empty_without_fabricating_student_targets(): void
    {
        $this->enableFeature('daily_plan');
        $this->enableFeature('mistake_notebook');

        $this->withToken(self::TOKEN)->getJson('/v1/adaptive-study')
            ->assertOk()
            ->assertJsonPath('data.today_mission.status', 'empty')
            ->assertJsonPath('data.today_mission.selected_count', 0)
            ->assertJsonPath('data.needs_practice.status', 'empty')
            ->assertJsonPath('data.mistakes.status', 'empty')
            ->assertJsonCount(0, 'data.mistakes.items');
    }

    public function test_backend_owned_scope_rejects_direct_context_selection_and_never_leaks_foreign_student_state(): void
    {
        $foreignUser = User::factory()->create(['role' => 'student']);
        $foreignContextId = (string) Str::ulid();
        $foreignSkillId = (string) Str::ulid();

        DB::table('user_academic_contexts')->insert([
            'id' => $foreignContextId,
            'user_id' => $foreignUser->getKey(),
            'academic_track_id' => LearningSliceSeeder::TRACK_ID,
            'status' => 'active',
            'activated_at' => now(),
            'archived_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('curriculum_nodes')->insert([
            'id' => $foreignSkillId,
            'academic_track_id' => LearningSliceSeeder::TRACK_ID,
            'parent_id' => LearningSliceSeeder::TOPIC_NODE_ID,
            'code' => 'FIXTURE:SKILL:FOREIGN',
            'type' => 'skill',
            'title' => $this->json(['en' => 'Foreign private skill']),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('student_skill_mastery_states')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $foreignUser->getKey(),
            'academic_context_id' => $foreignContextId,
            'skill_node_id' => $foreignSkillId,
            'algorithm_version' => 'mastery-v1',
            'mastery_score' => 0.10,
            'confidence' => 1.0,
            'evidence_count' => 10,
            'state_version' => 1,
            'last_evidence_at' => now(),
            'calculated_at' => now(),
            'archived_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withToken(self::TOKEN)
            ->getJson('/v1/adaptive-study?academic_context_id='.$foreignContextId)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED');

        $response = $this->withToken(self::TOKEN)->getJson('/v1/adaptive-study')->assertOk();
        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringNotContainsString($foreignContextId, $content);
        self::assertStringNotContainsString($foreignSkillId, $content);
        self::assertStringNotContainsString('Foreign private skill', $content);
    }

    private function enableFeature(string $featureKey): void
    {
        DB::table('learning_feature_controls')->insert([
            'id' => (string) Str::ulid(),
            'feature_key' => $featureKey,
            'state' => 'enabled',
            'scope' => null,
            'version' => 1,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{skill_id:string,quiz_id:string|null,attempt_question_id:string|null}
     */
    private function createSkillEvidence(
        string $code,
        float $score,
        bool $withAssessment,
        bool $withWrongAnswer,
    ): array {
        $skillId = (string) Str::ulid();
        DB::table('curriculum_nodes')->insert([
            'id' => $skillId,
            'academic_track_id' => LearningSliceSeeder::TRACK_ID,
            'parent_id' => LearningSliceSeeder::TOPIC_NODE_ID,
            'code' => $code,
            'type' => 'skill',
            'title' => $this->json([
                'en' => str_replace('FIXTURE:SKILL:', '', $code).' skill',
                'ar' => 'مهارة تجريبية',
                'fr' => 'Compétence de test',
            ]),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('student_skill_mastery_states')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => LearningSliceSeeder::USER_ID,
            'academic_context_id' => LearningSliceSeeder::CONTEXT_ID,
            'skill_node_id' => $skillId,
            'algorithm_version' => 'mastery-v1',
            'mastery_score' => $score,
            'confidence' => 0.90,
            'evidence_count' => 3,
            'state_version' => 1,
            'last_evidence_at' => now(),
            'calculated_at' => now(),
            'archived_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $withAssessment) {
            return ['skill_id' => $skillId, 'quiz_id' => null, 'attempt_question_id' => null];
        }

        $questionId = (string) Str::ulid();
        $quizId = (string) Str::ulid();
        DB::table('questions')->insert([
            'id' => $questionId,
            'curriculum_node_id' => $skillId,
            'learning_objective_id' => null,
            'content_version' => 1,
            'type' => 'short_text',
            'difficulty' => 'Medium',
            'generation_kind' => 'static',
            'template_contract' => null,
            'source_provenance' => null,
            'review_state' => 'approved',
            'review_version' => 1,
            'reviewed_by' => null,
            'reviewed_at' => now(),
            'published_by' => null,
            'published_at' => now(),
            'prompt' => $this->json(['en' => 'Which value needs recovery?']),
            'options' => null,
            'answer_contract' => $this->json(['kind' => 'short_text', 'correct' => 'DO_NOT_EXPOSE_CORRECT_ANSWER']),
            'explanation' => $this->json(['en' => 'DO_NOT_EXPOSE_EXPLANATION']),
            'maximum_score' => 1,
            'assessment_metadata' => null,
            'option_shuffle_safe' => false,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('quizzes')->insert([
            'id' => $quizId,
            'curriculum_node_id' => $skillId,
            'kind' => 'practice',
            'blueprint_version' => 1,
            'blueprint' => null,
            'title' => $this->json(['en' => 'Recovery practice']),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('quiz_questions')->insert([
            'quiz_id' => $quizId,
            'question_id' => $questionId,
            'source_position' => 1,
        ]);

        if (! $withWrongAnswer) {
            return ['skill_id' => $skillId, 'quiz_id' => $quizId, 'attempt_question_id' => null];
        }

        $attemptId = (string) Str::ulid();
        $attemptQuestionId = (string) Str::ulid();
        DB::table('attempts')->insert([
            'id' => $attemptId,
            'user_id' => LearningSliceSeeder::USER_ID,
            'academic_context_id' => LearningSliceSeeder::CONTEXT_ID,
            'quiz_id' => $quizId,
            'status' => 'graded',
            'seed_encrypted' => 'fixture-seed',
            'seed_fingerprint' => hash('sha256', 'fixture-seed'),
            'blueprint_version' => 1,
            'scope_snapshot' => $this->json([
                'curriculum_node_id' => $skillId,
                'quiz_kind' => 'practice',
                'mode' => 'practice',
                'hints_allowed' => true,
                'reveal_policy' => 'after_submit',
            ]),
            'ordering_algorithm' => 'fixture-order-v1',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'archived_at' => null,
            'score' => 0,
            'max_score' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('attempt_questions')->insert([
            'id' => $attemptQuestionId,
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
            'position' => 1,
            'question_snapshot' => $this->json([
                'schema_version' => 3,
                'source_question_id' => $questionId,
                'skill_node_id' => $skillId,
                'difficulty' => 'Medium',
                'type' => 'short_text',
                'prompt' => ['en' => 'Which value needs recovery?'],
                'response_contract' => ['kind' => 'short_text'],
                'grading_contract' => ['correct' => 'DO_NOT_EXPOSE_CORRECT_ANSWER'],
                'explanation' => ['en' => 'DO_NOT_EXPOSE_EXPLANATION'],
                'hints' => [],
                'mode_policy' => ['hints_allowed' => true, 'reveal_policy' => 'after_submit'],
                'maximum_score' => 1,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('attempt_answers')->insert([
            'id' => (string) Str::ulid(),
            'attempt_question_id' => $attemptQuestionId,
            'revision' => 1,
            'value' => $this->json('wrong'),
            'duration_ms' => 1200,
            'hint_count' => 0,
            'is_correct' => false,
            'awarded_score' => 0,
            'graded_at' => now(),
            'answered_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'skill_id' => $skillId,
            'quiz_id' => $quizId,
            'attempt_question_id' => $attemptQuestionId,
        ];
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

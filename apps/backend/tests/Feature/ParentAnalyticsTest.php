<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ParentAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'modrik-local-fixture-token';

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'modrik.fixture.enabled' => true,
            'modrik.fixture.bearer_token' => self::TOKEN,
            'modrik.fixture.user_id' => LearningSliceSeeder::USER_ID,
        ]);
        $this->seed(LearningSliceSeeder::class);

        DB::table('users')->where('id', LearningSliceSeeder::USER_ID)->update([
            'role' => 'parent',
            'account_status' => 'active',
            'deleted_at' => null,
        ]);
        $this->parent = User::query()->findOrFail(LearningSliceSeeder::USER_ID);
    }

    public function test_parent_sees_only_active_linked_children_and_non_parent_fails_closed(): void
    {
        $linked = $this->student('Linked child');
        $foreign = $this->student('Foreign child');
        $this->link($linked);

        $this->withToken(self::TOKEN)->getJson('/v1/parent/children')
            ->assertOk()
            ->assertJsonCount(1, 'data.children')
            ->assertJsonPath('data.children.0.id', (string) $linked->getKey())
            ->assertJsonPath('data.children.0.name', 'Linked child');

        $body = $this->withToken(self::TOKEN)->getJson('/v1/parent/children')->getContent();
        self::assertIsString($body);
        self::assertStringNotContainsString((string) $foreign->getKey(), $body);
        self::assertStringNotContainsString('Foreign child', $body);

        DB::table('users')->where('id', $this->parent->getKey())->update(['role' => 'student']);
        $this->withToken(self::TOKEN)->getJson('/v1/parent/children')
            ->assertForbidden()
            ->assertJsonPath('code', 'PARENT_ROLE_REQUIRED');
    }

    public function test_parent_child_direct_id_access_is_link_scoped_and_non_enumerable(): void
    {
        $linked = $this->student('Linked child');
        $foreign = $this->student('Foreign child');
        $this->link($linked);

        $this->withToken(self::TOKEN)
            ->getJson('/v1/parent/children/'.(string) $foreign->getKey().'/analytics')
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

        $this->withToken(self::TOKEN)
            ->getJson('/v1/parent/children/not-a-ulid/analytics')
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
    }

    public function test_parent_analytics_reconcile_to_authoritative_attempt_and_mastery_data(): void
    {
        $child = $this->student('Analytics child');
        $this->link($child);
        $contextId = $this->context($child);
        $skillId = (string) Str::ulid();

        DB::table('curriculum_nodes')->insert([
            'id' => $skillId,
            'academic_track_id' => LearningSliceSeeder::TRACK_ID,
            'parent_id' => LearningSliceSeeder::TOPIC_NODE_ID,
            'code' => 'FIXTURE:PARENT:SKILL',
            'type' => 'skill',
            'title' => $this->encodeJson([
                'en' => 'Fractions',
                'ar' => 'الكسور',
                'fr' => 'Fractions',
            ]),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stateId = (string) Str::ulid();
        DB::table('student_skill_mastery_states')->insert([
            'id' => $stateId,
            'user_id' => $child->getKey(),
            'academic_context_id' => $contextId,
            'skill_node_id' => $skillId,
            'algorithm_version' => 'mastery-v1',
            'mastery_score' => 0.85,
            'confidence' => 0.90,
            'evidence_count' => 4,
            'state_version' => 2,
            'last_evidence_at' => now()->subDays(20),
            'calculated_at' => now()->subDay(),
            'archived_at' => null,
            'created_at' => now()->subDays(20),
            'updated_at' => now()->subDay(),
        ]);

        foreach ([[1, 60.0], [2, 85.0]] as [$version, $score]) {
            DB::table('outbox_events')->insert([
                'id' => (string) Str::ulid(),
                'aggregate_type' => 'student_skill_mastery',
                'aggregate_id' => $stateId,
                'event_type' => 'mastery.state_recalculated',
                'payload' => $this->encodeJson([
                    'state_version' => $version,
                    'score_percent' => $score,
                    'confidence' => 0.90,
                ]),
                'occurred_at' => now()->subDays(3 - $version),
                'published_at' => now()->subDays(3 - $version),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        [$quizId, $questionId] = $this->assessment($skillId);
        $attemptId = (string) Str::ulid();
        $attemptQuestionId = (string) Str::ulid();
        DB::table('attempts')->insert([
            'id' => $attemptId,
            'user_id' => $child->getKey(),
            'academic_context_id' => $contextId,
            'quiz_id' => $quizId,
            'status' => 'graded',
            'seed_encrypted' => 'fixture-seed',
            'seed_fingerprint' => null,
            'blueprint_version' => 1,
            'scope_snapshot' => null,
            'ordering_algorithm' => 'modrik-fy-v1',
            'started_at' => now()->subHour(),
            'completed_at' => now()->subMinutes(30),
            'archived_at' => null,
            'score' => 1,
            'max_score' => 1,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subMinutes(30),
        ]);
        DB::table('attempt_questions')->insert([
            'id' => $attemptQuestionId,
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
            'position' => 1,
            'question_snapshot' => $this->encodeJson([
                'skill_node_id' => $skillId,
                'prompt' => ['en' => 'One half equals?'],
            ]),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);
        DB::table('attempt_answers')->insert([
            'id' => (string) Str::ulid(),
            'attempt_question_id' => $attemptQuestionId,
            'revision' => 1,
            'value' => $this->encodeJson('0.5'),
            'duration_ms' => 45000,
            'hint_count' => 0,
            'is_correct' => true,
            'awarded_score' => 1,
            'graded_at' => now()->subMinutes(30),
            'answered_at' => now()->subMinutes(31),
            'created_at' => now()->subMinutes(31),
            'updated_at' => now()->subMinutes(30),
        ]);

        $response = $this->withToken(self::TOKEN)
            ->getJson('/v1/parent/children/'.(string) $child->getKey().'/analytics')
            ->assertOk()
            ->assertJsonPath('data.state', 'active')
            ->assertJsonPath('data.child.id', (string) $child->getKey())
            ->assertJsonPath('data.activity.attempts_started', 1)
            ->assertJsonPath('data.activity.answered_questions', 1)
            ->assertJsonPath('data.activity.correct_questions', 1)
            ->assertJsonPath('data.activity.accuracy_percent', 100)
            ->assertJsonPath('data.activity.practice_time_seconds', 45)
            ->assertJsonPath('data.assessment_history.0.attempt_id', $attemptId)
            ->assertJsonPath('data.assessment_history.0.score_percent', 100)
            ->assertJsonPath('data.mastery.skills.0.skill_id', $skillId)
            ->assertJsonPath('data.mastery.skills.0.score_percent', 85)
            ->assertJsonPath('data.mastery.skills.0.trend.0.score_percent', 60)
            ->assertJsonPath('data.mastery.skills.0.trend.1.score_percent', 85)
            ->assertJsonPath('data.revision_attention.items.0.skill_id', $skillId)
            ->assertJsonPath('data.revision_attention.items.0.schedule_mode', 'current_mastery_base_interval');

        $body = $response->getContent();
        self::assertIsString($body);
        self::assertStringNotContainsString('rank', strtolower($body));
        self::assertStringNotContainsString('sibling', strtolower($body));
    }

    public function test_linked_child_without_active_context_returns_truthful_empty_state(): void
    {
        $child = $this->student('New child');
        $this->link($child);

        $this->withToken(self::TOKEN)
            ->getJson('/v1/parent/children/'.(string) $child->getKey().'/analytics')
            ->assertOk()
            ->assertJsonPath('data.state', 'no_active_context')
            ->assertJsonPath('data.activity.answered_questions', 0)
            ->assertJsonPath('data.activity.accuracy_percent', null)
            ->assertJsonCount(0, 'data.assessment_history')
            ->assertJsonCount(0, 'data.mastery.skills')
            ->assertJsonPath('data.revision_attention.due_count', 0);
    }

    private function student(string $name): User
    {
        return User::factory()->create([
            'name' => $name,
            'role' => 'student',
            'account_status' => 'active',
            'locale' => 'en',
            'deleted_at' => null,
        ]);
    }

    private function link(User $child): void
    {
        DB::table('parent_child_links')->insert([
            'id' => (string) Str::ulid(),
            'parent_user_id' => $this->parent->getKey(),
            'child_user_id' => $child->getKey(),
            'status' => 'active',
            'linked_at' => now(),
            'revoked_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function context(User $child): string
    {
        $contextId = (string) Str::ulid();
        DB::table('user_academic_contexts')->insert([
            'id' => $contextId,
            'user_id' => $child->getKey(),
            'academic_track_id' => LearningSliceSeeder::TRACK_ID,
            'status' => 'active',
            'activated_at' => now()->subDays(30),
            'archived_at' => null,
            'created_at' => now()->subDays(30),
            'updated_at' => now(),
        ]);

        return $contextId;
    }

    /** @return array{0:string,1:string} */
    private function assessment(string $skillId): array
    {
        $questionId = (string) Str::ulid();
        $quizId = (string) Str::ulid();

        DB::table('questions')->insert([
            'id' => $questionId,
            'curriculum_node_id' => $skillId,
            'learning_objective_id' => null,
            'content_version' => 1,
            'type' => 'numeric',
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
            'prompt' => $this->encodeJson(['en' => 'One half equals?']),
            'options' => null,
            'answer_contract' => $this->encodeJson(['kind' => 'numeric', 'value' => 0.5]),
            'explanation' => $this->encodeJson(['en' => 'One half is 0.5']),
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
            'title' => $this->encodeJson([
                'en' => 'Fractions practice',
                'ar' => 'تدريب الكسور',
                'fr' => 'Exercice sur les fractions',
            ]),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('quiz_questions')->insert([
            'quiz_id' => $quizId,
            'question_id' => $questionId,
            'source_position' => 1,
        ]);

        return [$quizId, $questionId];
    }

    private function encodeJson(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

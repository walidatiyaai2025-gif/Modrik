<?php

namespace Tests\Feature;

use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentContentCatalogueTest extends TestCase
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

    public function test_catalogue_requires_authentication_and_returns_only_published_active_context_content(): void
    {
        $this->getJson('/v1/content-catalogue')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'AUTHENTICATION_REQUIRED');

        $draftLessonId = (string) Str::ulid();
        DB::table('lessons')->insert([
            'id' => $draftLessonId,
            'curriculum_node_id' => LearningSliceSeeder::TOPIC_NODE_ID,
            'slug' => 'draft-hidden-lesson',
            'content_version' => 1,
            'title' => json_encode(['en' => 'Draft hidden lesson', 'ar' => 'درس مسودة', 'fr' => 'Leçon brouillon'], JSON_THROW_ON_ERROR),
            'status' => 'draft',
            'published_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $draftQuizId = (string) Str::ulid();
        DB::table('quizzes')->insert([
            'id' => $draftQuizId,
            'curriculum_node_id' => LearningSliceSeeder::TOPIC_NODE_ID,
            'kind' => 'mock_exam',
            'blueprint_version' => 1,
            'title' => json_encode(['en' => 'Draft mock', 'ar' => 'اختبار مسودة', 'fr' => 'Examen brouillon'], JSON_THROW_ON_ERROR),
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken(self::TOKEN)->getJson('/v1/content-catalogue')
            ->assertOk()
            ->assertJsonPath('data.state', 'active')
            ->assertJsonPath('data.context.academic_track_id', LearningSliceSeeder::TRACK_ID)
            ->assertJsonPath('data.subjects.0.reference', 'FIXTURE:SUBJECT:GENERAL-STUDY')
            ->assertJsonPath('data.subjects.0.children.0.reference', 'FIXTURE:TOPIC:STUDY-PLAN')
            ->assertJsonPath('data.subjects.0.children.0.lessons.0.id', '01J00000000000000000000003')
            ->assertJsonPath('data.subjects.0.children.0.assessments.0.id', LearningSliceSeeder::QUIZ_ID)
            ->assertJsonPath('data.subjects.0.children.0.assessments.0.kind', 'practice');

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringNotContainsString($draftLessonId, $content);
        $this->assertStringNotContainsString($draftQuizId, $content);
    }

    public function test_catalogue_supports_subject_filter_and_rejects_invalid_reference(): void
    {
        $this->withToken(self::TOKEN)
            ->getJson('/v1/content-catalogue?subject_reference=FIXTURE%3ASUBJECT%3AGENERAL-STUDY')
            ->assertOk()
            ->assertJsonCount(1, 'data.subjects');

        $this->withToken(self::TOKEN)
            ->getJson('/v1/content-catalogue?subject_reference=SUBJECT%3ANOT-PUBLISHED')
            ->assertOk()
            ->assertJsonCount(0, 'data.subjects');

        $this->withToken(self::TOKEN)
            ->getJson('/v1/content-catalogue?subject_reference=%3Cscript%3E')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_published_lesson_without_practice_quiz_still_opens(): void
    {
        $lessonId = (string) Str::ulid();
        $blockId = (string) Str::ulid();
        DB::table('lessons')->insert([
            'id' => $lessonId,
            'curriculum_node_id' => LearningSliceSeeder::SUBJECT_NODE_ID,
            'slug' => 'published-reading-only',
            'content_version' => 1,
            'title' => json_encode(['en' => 'Reading only', 'ar' => 'قراءة فقط', 'fr' => 'Lecture uniquement'], JSON_THROW_ON_ERROR),
            'status' => 'published',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('lesson_blocks')->insert([
            'id' => $blockId,
            'lesson_id' => $lessonId,
            'position' => 1,
            'type' => 'rich_text',
            'content' => json_encode(['en' => 'Published content', 'ar' => 'محتوى منشور', 'fr' => 'Contenu publié'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withToken(self::TOKEN)->getJson('/v1/lessons/'.$lessonId)
            ->assertOk()
            ->assertJsonPath('data.id', $lessonId)
            ->assertJsonPath('data.practice_quiz_id', null)
            ->assertJsonPath('data.blocks.0.id', $blockId);
    }
}

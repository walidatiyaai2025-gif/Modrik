<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class LearningSliceSeeder extends Seeder
{
    public const USER_ID = '01J00000000000000000000030';

    public const TRACK_ID = '01J00000000000000000000031';

    public const CONTEXT_ID = '01J00000000000000000000032';

    public const SUBJECT_NODE_ID = '01J00000000000000000000033';

    public const TOPIC_NODE_ID = '01J00000000000000000000034';

    public const TRACK_AUTHORIZATION_ID = '01J00000000000000000000035';

    public const QUIZ_ID = '01J00000000000000000000020';

    /**
     * @throws JsonException
     */
    public function run(): void
    {
        $fixtureCandidates = [
            base_path('../../tests/fixtures/content-pack/v1/valid/content-pack.json'),
            resource_path('fixtures/content-pack/v1/valid/content-pack.json'),
        ];
        $fixturePath = null;

        foreach ($fixtureCandidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                $fixturePath = $candidate;
                break;
            }
        }

        if ($fixturePath === null) {
            throw new RuntimeException('Unable to locate the synthetic content fixture in the repository or packaged Backend payload.');
        }

        $contents = file_get_contents($fixturePath);

        if ($contents === false) {
            throw new RuntimeException("Unable to read synthetic content fixture: {$fixturePath}");
        }

        /** @var array{
         *   academic_scope: array{track_reference: string, board_reference: ?string, syllabus_version: ?string, year_level: string},
         *   curriculum_nodes: list<array{reference: string, parent_reference: ?string, type: string, title: array<string, string>}>,
         *   lessons: list<array{id: string, curriculum_node_reference: string, slug: string, content_version: int, title: array<string, string>, blocks: list<array{id: string, position: int, type: string, content: array<string, string>}>}>,
         *   questions: list<array{id: string, curriculum_node_reference: string, content_version: int, type: string, prompt: array<string, string>, options?: list<array{id: string, label: array<string, string>}>, answer_contract: array<string, mixed>, explanation: array<string, string>, maximum_score: int|float}>,
         *   quizzes: list<array{id: string, curriculum_node_reference: string, kind: string, blueprint_version: int, title: array<string, string>, question_ids: list<string>}>
         * } $pack
         */
        $pack = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $now = now();

        User::query()->updateOrCreate(
            ['id' => self::USER_ID],
            [
                'name' => 'Fixture Learner',
                'email' => 'learner@fixture.invalid',
                'email_verified_at' => $now,
                'locale' => 'en',
                'role' => 'student',
                'password' => Hash::make(Str::random(64)),
            ],
        );

        DB::table('academic_tracks')->updateOrInsert(
            ['id' => self::TRACK_ID],
            [
                'code' => $pack['academic_scope']['track_reference'],
                'board_reference' => $pack['academic_scope']['board_reference'],
                'syllabus_version' => $pack['academic_scope']['syllabus_version'],
                'year_level' => $pack['academic_scope']['year_level'],
                'title' => $this->json([
                    'en' => 'Synthetic fixture track',
                    'ar' => 'مسار تجريبي اصطناعي',
                    'fr' => 'Parcours synthétique de test',
                ]),
                'is_fixture' => true,
                'availability_state' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('academic_track_authorizations')->updateOrInsert(
            ['id' => self::TRACK_AUTHORIZATION_ID],
            [
                'user_id' => self::USER_ID,
                'academic_track_id' => self::TRACK_ID,
                'sort_order' => 100,
                'authorized_at' => $now,
                'revoked_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $nodeIds = [
            'FIXTURE:SUBJECT:GENERAL-STUDY' => self::SUBJECT_NODE_ID,
            'FIXTURE:TOPIC:STUDY-PLAN' => self::TOPIC_NODE_ID,
        ];

        foreach ($pack['curriculum_nodes'] as $node) {
            $parentId = $node['parent_reference'] === null ? null : ($nodeIds[$node['parent_reference']] ?? null);
            DB::table('curriculum_nodes')->updateOrInsert(
                ['id' => $nodeIds[$node['reference']]],
                [
                    'academic_track_id' => self::TRACK_ID,
                    'parent_id' => $parentId,
                    'code' => $node['reference'],
                    'type' => $node['type'],
                    'title' => $this->json($node['title']),
                    'status' => 'published',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        DB::table('user_academic_contexts')->updateOrInsert(
            ['id' => self::CONTEXT_ID],
            [
                'user_id' => self::USER_ID,
                'academic_track_id' => self::TRACK_ID,
                'status' => 'active',
                'activated_at' => $now,
                'archived_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        foreach ($pack['lessons'] as $lesson) {
            DB::table('lessons')->updateOrInsert(
                ['id' => $lesson['id']],
                [
                    'curriculum_node_id' => $nodeIds[$lesson['curriculum_node_reference']],
                    'slug' => $lesson['slug'],
                    'content_version' => $lesson['content_version'],
                    'title' => $this->json($lesson['title']),
                    'status' => 'published',
                    'published_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            foreach ($lesson['blocks'] as $block) {
                DB::table('lesson_blocks')->updateOrInsert(
                    ['id' => $block['id']],
                    [
                        'lesson_id' => $lesson['id'],
                        'position' => $block['position'],
                        'type' => $block['type'],
                        'content' => $this->json($block['content']),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }

        foreach ($pack['questions'] as $question) {
            DB::table('questions')->updateOrInsert(
                ['id' => $question['id']],
                [
                    'curriculum_node_id' => $nodeIds[$question['curriculum_node_reference']],
                    'content_version' => $question['content_version'],
                    'type' => $question['type'],
                    'prompt' => $this->json($question['prompt']),
                    'options' => array_key_exists('options', $question) ? $this->json($question['options']) : null,
                    'answer_contract' => $this->json($question['answer_contract']),
                    'explanation' => $this->json($question['explanation']),
                    'maximum_score' => $question['maximum_score'],
                    'status' => 'published',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        foreach ($pack['quizzes'] as $quiz) {
            DB::table('quizzes')->updateOrInsert(
                ['id' => $quiz['id']],
                [
                    'curriculum_node_id' => $nodeIds[$quiz['curriculum_node_reference']],
                    'kind' => $quiz['kind'],
                    'blueprint_version' => $quiz['blueprint_version'],
                    'title' => $this->json($quiz['title']),
                    'status' => 'published',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            foreach ($quiz['question_ids'] as $index => $questionId) {
                DB::table('quiz_questions')->updateOrInsert(
                    ['quiz_id' => $quiz['id'], 'question_id' => $questionId],
                    ['source_position' => $index + 1],
                );
            }
        }
    }

    /**
     * @throws JsonException
     */
    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

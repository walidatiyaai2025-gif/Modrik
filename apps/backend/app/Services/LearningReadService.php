<?php

namespace App\Services;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use JsonException;

final class LearningReadService
{
    /** @return array{user_id: string, locale: string, roles: list<string>} */
    public function session(User $user): array
    {
        return [
            'user_id' => (string) $user->getKey(),
            'locale' => (string) $user->locale,
            'roles' => [(string) $user->role],
        ];
    }

    /** @return array<string, mixed> */
    public function academicContext(User $user): array
    {
        $context = DB::table('user_academic_contexts as contexts')
            ->join('academic_tracks as tracks', 'tracks.id', '=', 'contexts.academic_track_id')
            ->where('contexts.user_id', $user->getKey())
            ->where('contexts.status', 'active')
            ->select(['contexts.id as context_id', 'tracks.id as academic_track_id', 'tracks.year_level', 'contexts.activated_at'])
            ->first();

        if ($context === null) {
            return ['state' => 'onboarding_required'];
        }

        /** @var array{context_id: string, academic_track_id: string, year_level: string, activated_at: string} $row */
        $row = (array) $context;

        return [
            'state' => 'active',
            'context_id' => $row['context_id'],
            'academic_track_id' => $row['academic_track_id'],
            'year_level' => $row['year_level'],
            'activated_at' => CarbonImmutable::parse($row['activated_at'])->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function lesson(User $user, string $lessonId): array
    {
        $lesson = DB::table('lessons')
            ->join('curriculum_nodes', 'curriculum_nodes.id', '=', 'lessons.curriculum_node_id')
            ->join('user_academic_contexts', function ($join) use ($user): void {
                $join->on('user_academic_contexts.academic_track_id', '=', 'curriculum_nodes.academic_track_id')
                    ->where('user_academic_contexts.user_id', '=', $user->getKey())
                    ->where('user_academic_contexts.status', '=', 'active');
            })
            ->where('lessons.id', $lessonId)
            ->where('lessons.status', 'published')
            ->select(['lessons.id', 'lessons.curriculum_node_id', 'lessons.content_version', 'lessons.title'])
            ->first();

        if ($lesson === null) {
            throw new ApiProblemException(404, 'RESOURCE_NOT_FOUND', 'Resource not found', 'The published lesson is unavailable in the active academic context.');
        }

        /** @var array{id: string, curriculum_node_id: string, content_version: int, title: string} $lessonRow */
        $lessonRow = (array) $lesson;
        $quizId = DB::table('quizzes')
            ->where('curriculum_node_id', $lessonRow['curriculum_node_id'])
            ->where('kind', 'practice')
            ->where('status', 'published')
            ->value('id');

        if (! is_string($quizId)) {
            throw new ApiProblemException(404, 'RESOURCE_NOT_FOUND', 'Resource not found', 'No published practice is available for this lesson.');
        }

        $blocks = DB::table('lesson_blocks')
            ->where('lesson_id', $lessonId)
            ->orderBy('position')
            ->get(['id', 'position', 'type', 'content'])
            ->map(function (object $block): array {
                /** @var array{id: string, position: int, type: string, content: string} $row */
                $row = (array) $block;

                return [
                    'id' => $row['id'],
                    'position' => (int) $row['position'],
                    'type' => $row['type'],
                    'content' => $this->decode($row['content']),
                ];
            })
            ->values()
            ->all();

        return [
            'id' => $lessonRow['id'],
            'curriculum_node_id' => $lessonRow['curriculum_node_id'],
            'content_version' => (int) $lessonRow['content_version'],
            'title' => $this->decode($lessonRow['title']),
            'practice_quiz_id' => $quizId,
            'blocks' => $blocks,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function progress(User $user): array
    {
        return array_values(DB::table('progress_snapshots as progress')
            ->join('user_academic_contexts as contexts', function ($join) use ($user): void {
                $join->on('contexts.id', '=', 'progress.academic_context_id')
                    ->where('contexts.user_id', '=', $user->getKey())
                    ->where('contexts.status', '=', 'active');
            })
            ->where('progress.user_id', $user->getKey())
            ->whereNull('progress.archived_at')
            ->orderBy('progress.curriculum_node_id')
            ->get(['progress.academic_context_id', 'progress.curriculum_node_id', 'progress.mastery', 'progress.source_version', 'progress.calculated_at'])
            ->map(function (object $progress): array {
                /** @var array{academic_context_id: string, curriculum_node_id: string, mastery: string|float, source_version: int, calculated_at: string} $row */
                $row = (array) $progress;

                return [
                    'academic_context_id' => $row['academic_context_id'],
                    'curriculum_node_id' => $row['curriculum_node_id'],
                    'mastery' => (float) $row['mastery'],
                    'source_version' => (int) $row['source_version'],
                    'calculated_at' => CarbonImmutable::parse($row['calculated_at'])->toIso8601String(),
                ];
            })
            ->values()
            ->all());
    }

    /**
     * @return array<string, mixed>|list<mixed>
     *
     * @throws JsonException
     */
    private function decode(string $json): array
    {
        /** @var array<string, mixed>|list<mixed> $decoded */
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }
}

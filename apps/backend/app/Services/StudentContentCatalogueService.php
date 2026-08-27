<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use JsonException;

final class StudentContentCatalogueService
{
    /**
     * Return published content for the learner's Backend-owned active academic context.
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function catalogue(User $user, ?string $subjectReference = null): array
    {
        $context = DB::table('user_academic_contexts as contexts')
            ->join('academic_tracks as tracks', 'tracks.id', '=', 'contexts.academic_track_id')
            ->where('contexts.user_id', $user->getKey())
            ->where('contexts.status', 'active')
            ->select([
                'contexts.id as context_id',
                'tracks.id as academic_track_id',
                'tracks.code as track_reference',
                'tracks.year_level',
                'tracks.title as track_title',
                'tracks.is_fixture',
                'tracks.availability_state',
            ])
            ->first();

        if (! $context instanceof \stdClass) {
            return $this->onboarding();
        }

        if ((string) $context->availability_state !== 'published'
            || ((bool) $context->is_fixture && ! (bool) config('modrik.fixture.enabled'))) {
            return $this->activeEmpty($context);
        }

        $nodes = DB::table('curriculum_nodes')
            ->where('academic_track_id', $context->academic_track_id)
            ->where('status', 'published')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'code', 'type', 'title']);

        $nodeById = [];
        $childrenByParent = [];
        $subjectIds = [];
        foreach ($nodes as $node) {
            $id = (string) $node->id;
            $nodeById[$id] = $node;
            $parentKey = is_string($node->parent_id) ? $node->parent_id : '';
            $childrenByParent[$parentKey][] = $id;
            if ((string) $node->type === 'subject'
                && ($subjectReference === null || hash_equals((string) $node->code, $subjectReference))) {
                $subjectIds[] = $id;
            }
        }

        if ($subjectIds === []) {
            return $this->activeEmpty($context);
        }

        $visibleNodeIds = [];
        $collect = function (string $nodeId) use (&$collect, &$visibleNodeIds, $childrenByParent): void {
            if (isset($visibleNodeIds[$nodeId])) {
                return;
            }
            $visibleNodeIds[$nodeId] = true;
            foreach ($childrenByParent[$nodeId] ?? [] as $childId) {
                $collect($childId);
            }
        };
        foreach ($subjectIds as $subjectId) {
            $collect($subjectId);
        }
        $visibleIds = array_keys($visibleNodeIds);

        $lessonsByNode = [];
        $lessonCount = 0;
        foreach (DB::table('lessons')
            ->whereIn('curriculum_node_id', $visibleIds)
            ->where('status', 'published')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'curriculum_node_id', 'slug', 'content_version', 'title', 'published_at']) as $lesson) {
            $nodeId = (string) $lesson->curriculum_node_id;
            $lessonsByNode[$nodeId][] = [
                'id' => (string) $lesson->id,
                'slug' => (string) $lesson->slug,
                'content_version' => (int) $lesson->content_version,
                'title' => $this->decode($lesson->title),
                'published_at' => $lesson->published_at,
            ];
            $lessonCount++;
        }

        $assessmentsByNode = [];
        $assessmentCount = 0;
        foreach (DB::table('quizzes')
            ->whereIn('curriculum_node_id', $visibleIds)
            ->where('status', 'published')
            ->whereIn('kind', ['practice', 'quiz', 'mock_exam'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'curriculum_node_id', 'kind', 'blueprint_version', 'title']) as $quiz) {
            $nodeId = (string) $quiz->curriculum_node_id;
            $assessmentsByNode[$nodeId][] = [
                'id' => (string) $quiz->id,
                'kind' => (string) $quiz->kind,
                'blueprint_version' => (int) $quiz->blueprint_version,
                'title' => $this->decode($quiz->title),
            ];
            $assessmentCount++;
        }

        $buildNode = function (string $nodeId) use (&$buildNode, $nodeById, $childrenByParent, $visibleNodeIds, $lessonsByNode, $assessmentsByNode): array {
            $node = $nodeById[$nodeId];
            $children = [];
            foreach ($childrenByParent[$nodeId] ?? [] as $childId) {
                if (isset($visibleNodeIds[$childId])) {
                    $children[] = $buildNode($childId);
                }
            }

            return [
                'id' => $nodeId,
                'reference' => (string) $node->code,
                'type' => (string) $node->type,
                'title' => $this->decode($node->title),
                'lessons' => $lessonsByNode[$nodeId] ?? [],
                'assessments' => $assessmentsByNode[$nodeId] ?? [],
                'children' => $children,
            ];
        };

        $subjects = array_map($buildNode, $subjectIds);

        return [
            'state' => 'active',
            'context' => $this->contextPayload($context),
            'subjects' => array_values($subjects),
            'counts' => [
                'subjects' => count($subjects),
                'lessons' => $lessonCount,
                'assessments' => $assessmentCount,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function onboarding(): array
    {
        return [
            'state' => 'onboarding_required',
            'subjects' => [],
            'counts' => ['subjects' => 0, 'lessons' => 0, 'assessments' => 0],
        ];
    }

    /** @return array<string, mixed> */
    private function activeEmpty(\stdClass $context): array
    {
        return [
            'state' => 'active',
            'context' => $this->contextPayload($context),
            'subjects' => [],
            'counts' => ['subjects' => 0, 'lessons' => 0, 'assessments' => 0],
        ];
    }

    /** @return array<string, mixed> */
    private function contextPayload(\stdClass $context): array
    {
        return [
            'context_id' => (string) $context->context_id,
            'academic_track_id' => (string) $context->academic_track_id,
            'track_reference' => (string) $context->track_reference,
            'year_level' => (string) $context->year_level,
            'track_title' => $this->decode($context->track_title),
        ];
    }

    /**
     * @return array<string, mixed>|list<mixed>
     *
     * @throws JsonException
     */
    private function decode(mixed $json): array
    {
        if (is_array($json)) {
            return $json;
        }

        /** @var array<string, mixed>|list<mixed> $decoded */
        $decoded = json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }
}

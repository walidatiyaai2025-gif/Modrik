<?php

namespace App\Services;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use JsonException;
use stdClass;

final class ParentAnalyticsService
{
    private const HISTORY_LIMIT = 20;

    public function __construct(
        private readonly MasteryBandPolicy $bands,
        private readonly SpacedRepetitionScheduler $revisionScheduler,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function children(User $parent): array
    {
        $this->requireParent($parent);

        return array_values(DB::table('parent_child_links as links')
            ->join('users as children', 'children.id', '=', 'links.child_user_id')
            ->leftJoin('user_academic_contexts as contexts', function ($join): void {
                $join->on('contexts.user_id', '=', 'children.id')
                    ->where('contexts.status', '=', 'active')
                    ->whereNull('contexts.archived_at');
            })
            ->leftJoin('academic_tracks as tracks', 'tracks.id', '=', 'contexts.academic_track_id')
            ->where('links.parent_user_id', $parent->getKey())
            ->where('links.status', 'active')
            ->whereNull('links.revoked_at')
            ->where('children.role', 'student')
            ->where('children.account_status', 'active')
            ->whereNull('children.deleted_at')
            ->orderBy('children.name')
            ->orderBy('children.id')
            ->get([
                'children.id',
                'children.name',
                'children.locale',
                'contexts.id as context_id',
                'tracks.year_level',
                'tracks.title as track_title',
            ])
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
                'locale' => (string) $row->locale,
                'academic_context' => $row->context_id === null ? null : [
                    'context_id' => (string) $row->context_id,
                    'year_level' => (string) $row->year_level,
                    'track_title' => $this->decode((string) $row->track_title),
                ],
            ])
            ->all());
    }

    /** @return array<string, mixed> */
    public function snapshot(User $parent, string $childId): array
    {
        $this->requireParent($parent);
        $child = $this->authorizedChild($parent, $childId);

        $context = DB::table('user_academic_contexts as contexts')
            ->join('academic_tracks as tracks', 'tracks.id', '=', 'contexts.academic_track_id')
            ->where('contexts.user_id', $childId)
            ->where('contexts.status', 'active')
            ->whereNull('contexts.archived_at')
            ->select([
                'contexts.id as context_id',
                'tracks.id as academic_track_id',
                'tracks.code as track_reference',
                'tracks.year_level',
                'tracks.title as track_title',
            ])
            ->first();

        if ($context === null) {
            return [
                'state' => 'no_active_context',
                'child' => $this->childSummary($child),
                'activity' => $this->emptyActivity(),
                'assessment_history' => [],
                'mastery' => $this->emptyMastery(),
                'revision_attention' => $this->emptyRevisionAttention(),
            ];
        }

        $contextId = (string) $context->context_id;
        $trackId = (string) $context->academic_track_id;
        $nodes = $this->nodeMap($trackId);
        $mastery = $this->mastery($childId, $contextId, $nodes);

        return [
            'state' => 'active',
            'child' => $this->childSummary($child),
            'academic_context' => [
                'context_id' => $contextId,
                'academic_track_id' => $trackId,
                'track_reference' => (string) $context->track_reference,
                'year_level' => (string) $context->year_level,
                'track_title' => $this->decode((string) $context->track_title),
            ],
            'activity' => $this->activity($childId, $contextId),
            'assessment_history' => $this->assessmentHistory($childId, $contextId),
            'mastery' => $mastery,
            'revision_attention' => $this->revisionAttention($mastery['skills']),
        ];
    }

    private function requireParent(User $parent): void
    {
        if ((string) $parent->role !== 'parent'
            || (string) $parent->account_status !== 'active'
            || $parent->deleted_at !== null) {
            throw new ApiProblemException(
                403,
                'PARENT_ROLE_REQUIRED',
                'Parent access required',
                'This resource is available only to an active parent account.',
            );
        }
    }

    private function authorizedChild(User $parent, string $childId): User
    {
        $authorized = DB::table('parent_child_links')
            ->where('parent_user_id', $parent->getKey())
            ->where('child_user_id', $childId)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->exists();

        if (! $authorized) {
            throw new ApiProblemException(
                404,
                'RESOURCE_NOT_FOUND',
                'Resource not found',
                'The child analytics resource is unavailable.',
            );
        }

        $child = User::query()
            ->whereKey($childId)
            ->where('role', 'student')
            ->where('account_status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if (! $child instanceof User) {
            throw new ApiProblemException(
                404,
                'RESOURCE_NOT_FOUND',
                'Resource not found',
                'The child analytics resource is unavailable.',
            );
        }

        return $child;
    }

    /** @return array<string, mixed> */
    private function childSummary(User $child): array
    {
        return [
            'id' => (string) $child->getKey(),
            'name' => (string) $child->name,
            'locale' => (string) $child->locale,
        ];
    }

    /** @return array<string, mixed> */
    private function activity(string $childId, string $contextId): array
    {
        $attempts = DB::table('attempts')
            ->where('user_id', $childId)
            ->where('academic_context_id', $contextId)
            ->whereNull('archived_at')
            ->orderBy('started_at')
            ->get(['id', 'status', 'started_at', 'completed_at']);

        $answers = DB::table('attempt_answers as answers')
            ->join('attempt_questions as questions', 'questions.id', '=', 'answers.attempt_question_id')
            ->join('attempts', 'attempts.id', '=', 'questions.attempt_id')
            ->where('attempts.user_id', $childId)
            ->where('attempts.academic_context_id', $contextId)
            ->whereNull('attempts.archived_at')
            ->orderBy('questions.id')
            ->orderBy('answers.revision')
            ->get([
                'questions.id as attempt_question_id',
                'answers.revision',
                'answers.duration_ms',
                'answers.is_correct',
                'answers.answered_at',
                'answers.graded_at',
            ]);

        /** @var array<string, object> $latest */
        $latest = [];
        foreach ($answers as $answer) {
            $latest[(string) $answer->attempt_question_id] = $answer;
        }

        $answered = count($latest);
        $graded = 0;
        $correct = 0;
        $practiceMilliseconds = 0;
        $activityDays = [];
        $lastActivity = null;

        foreach ($latest as $answer) {
            $practiceMilliseconds += max(0, (int) $answer->duration_ms);
            if ($answer->is_correct !== null) {
                $graded++;
                if ((bool) $answer->is_correct) {
                    $correct++;
                }
            }
            $timestamp = $answer->graded_at ?? $answer->answered_at;
            if (is_string($timestamp)) {
                $time = CarbonImmutable::parse($timestamp);
                $activityDays[$time->toDateString()] = true;
                if ($lastActivity === null || $time->greaterThan($lastActivity)) {
                    $lastActivity = $time;
                }
            }
        }

        foreach ($attempts as $attempt) {
            $time = CarbonImmutable::parse((string) $attempt->started_at);
            $activityDays[$time->toDateString()] = true;
            if ($lastActivity === null || $time->greaterThan($lastActivity)) {
                $lastActivity = $time;
            }
        }

        return [
            'attempts_started' => $attempts->count(),
            'attempts_completed' => $attempts->filter(
                static fn (object $attempt): bool => in_array((string) $attempt->status, ['submitted', 'graded'], true),
            )->count(),
            'answered_questions' => $answered,
            'graded_questions' => $graded,
            'correct_questions' => $correct,
            'accuracy_percent' => $graded === 0 ? null : round(($correct / $graded) * 100, 2),
            'practice_time_seconds' => (int) round($practiceMilliseconds / 1000),
            'active_days' => count($activityDays),
            'last_activity_at' => $lastActivity?->toIso8601String(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function assessmentHistory(string $childId, string $contextId): array
    {
        return array_values(DB::table('attempts as attempts')
            ->join('quizzes as quizzes', 'quizzes.id', '=', 'attempts.quiz_id')
            ->where('attempts.user_id', $childId)
            ->where('attempts.academic_context_id', $contextId)
            ->whereNull('attempts.archived_at')
            ->whereIn('attempts.status', ['submitted', 'graded'])
            ->orderByDesc('attempts.completed_at')
            ->orderByDesc('attempts.id')
            ->limit(self::HISTORY_LIMIT)
            ->get([
                'attempts.id',
                'attempts.status',
                'attempts.score',
                'attempts.max_score',
                'attempts.started_at',
                'attempts.completed_at',
                'quizzes.kind',
                'quizzes.title',
            ])
            ->map(fn (object $row): array => [
                'attempt_id' => (string) $row->id,
                'kind' => (string) $row->kind,
                'title' => $this->decode((string) $row->title),
                'status' => (string) $row->status,
                'score' => $row->score === null ? null : (float) $row->score,
                'max_score' => $row->max_score === null ? null : (float) $row->max_score,
                'score_percent' => $row->score === null || $row->max_score === null || (float) $row->max_score <= 0
                    ? null
                    : round(((float) $row->score / (float) $row->max_score) * 100, 2),
                'started_at' => CarbonImmutable::parse((string) $row->started_at)->toIso8601String(),
                'completed_at' => $row->completed_at === null
                    ? null
                    : CarbonImmutable::parse((string) $row->completed_at)->toIso8601String(),
            ])
            ->all());
    }

    /**
     * @param array<string, stdClass> $nodes
     * @return array<string, mixed>
     */
    private function mastery(string $childId, string $contextId, array $nodes): array
    {
        $skills = [];
        $topicBuckets = [];
        $subjectBuckets = [];

        $states = DB::table('student_skill_mastery_states')
            ->where('user_id', $childId)
            ->where('academic_context_id', $contextId)
            ->whereNull('archived_at')
            ->where('evidence_count', '>', 0)
            ->orderBy('skill_node_id')
            ->get([
                'id',
                'skill_node_id',
                'mastery_score',
                'confidence',
                'evidence_count',
                'last_evidence_at',
                'calculated_at',
            ]);

        foreach ($states as $state) {
            $skillId = (string) $state->skill_node_id;
            $skill = $nodes[$skillId] ?? null;
            if ($skill === null || (string) $skill->type !== 'skill') {
                continue;
            }

            $topic = $this->ancestor($skillId, 'topic', $nodes);
            $subject = $this->ancestor($skillId, 'subject', $nodes);
            if ($subject === null) {
                continue;
            }

            $score = round(((float) ($state->mastery_score ?? 0.0)) * 100, 2);
            $band = $this->bands->band($score, (string) app()->environment());
            $item = [
                'skill_id' => $skillId,
                'skill_reference' => (string) $skill->code,
                'skill_title' => $this->decode((string) $skill->title),
                'topic' => $topic === null ? null : [
                    'id' => (string) $topic->id,
                    'reference' => (string) $topic->code,
                    'title' => $this->decode((string) $topic->title),
                ],
                'subject' => [
                    'id' => (string) $subject->id,
                    'reference' => (string) $subject->code,
                    'title' => $this->decode((string) $subject->title),
                ],
                'score_percent' => $score,
                'display_band' => $band,
                'confidence' => round((float) ($state->confidence ?? 0.0), 4),
                'evidence_count' => (int) $state->evidence_count,
                'last_evidence_at' => $state->last_evidence_at === null
                    ? null
                    : CarbonImmutable::parse((string) $state->last_evidence_at)->toIso8601String(),
                'calculated_at' => $state->calculated_at === null
                    ? null
                    : CarbonImmutable::parse((string) $state->calculated_at)->toIso8601String(),
                'trend' => $this->masteryHistory((string) $state->id),
            ];
            $skills[] = $item;
            $this->bucket($topicBuckets, $topic, $score);
            $this->bucket($subjectBuckets, $subject, $score);
        }

        return [
            'skills' => $skills,
            'topics' => $this->summaries($topicBuckets),
            'subjects' => $this->summaries($subjectBuckets),
            'strong' => array_values(array_filter($skills, static fn (array $item): bool => $item['display_band'] === 'strong')),
            'attention' => array_values(array_filter($skills, static fn (array $item): bool => in_array($item['display_band'], ['critical', 'weak'], true))),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function masteryHistory(string $stateId): array
    {
        return array_values(DB::table('outbox_events')
            ->where('aggregate_type', 'student_skill_mastery')
            ->where('aggregate_id', $stateId)
            ->where('event_type', 'mastery.state_recalculated')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get(['payload', 'occurred_at'])
            ->map(function (object $event): array {
                $payload = $this->decode((string) $event->payload);

                return [
                    'occurred_at' => CarbonImmutable::parse((string) $event->occurred_at)->toIso8601String(),
                    'score_percent' => isset($payload['score_percent']) ? (float) $payload['score_percent'] : null,
                    'confidence' => isset($payload['confidence']) ? (float) $payload['confidence'] : null,
                    'state_version' => isset($payload['state_version']) ? (int) $payload['state_version'] : null,
                ];
            })
            ->reverse()
            ->values()
            ->all());
    }

    /** @param list<array<string, mixed>> $skills */
    private function revisionAttention(array $skills): array
    {
        $items = [];
        $now = CarbonImmutable::now();

        foreach ($skills as $skill) {
            $lastEvidence = $skill['last_evidence_at'] ?? null;
            if (! is_string($lastEvidence) || $lastEvidence === '') {
                continue;
            }
            $band = (string) $skill['display_band'];
            $schedule = $this->revisionScheduler->schedule(
                $band,
                true,
                null,
                CarbonImmutable::parse($lastEvidence),
            );
            $due = CarbonImmutable::parse($schedule['due_at']);
            $items[] = [
                'skill_id' => $skill['skill_id'],
                'skill_reference' => $skill['skill_reference'],
                'skill_title' => $skill['skill_title'],
                'subject' => $skill['subject'],
                'display_band' => $band,
                'score_percent' => $skill['score_percent'],
                'last_evidence_at' => $lastEvidence,
                'due_at' => $schedule['due_at'],
                'due_now' => $due->lessThanOrEqualTo($now),
                'algorithm_version' => $schedule['algorithm_version'],
                'schedule_mode' => 'current_mastery_base_interval',
            ];
        }

        usort($items, static function (array $left, array $right): int {
            $due = strcmp((string) $left['due_at'], (string) $right['due_at']);

            return $due !== 0 ? $due : strcmp((string) $left['skill_id'], (string) $right['skill_id']);
        });

        return [
            'algorithm_version' => SpacedRepetitionScheduler::ALGORITHM_VERSION,
            'due_count' => count(array_filter($items, static fn (array $item): bool => $item['due_now'])),
            'attention_count' => count(array_filter($items, static fn (array $item): bool => in_array($item['display_band'], ['critical', 'weak'], true))),
            'items' => $items,
        ];
    }

    /** @return array<string, stdClass> */
    private function nodeMap(string $trackId): array
    {
        $nodes = [];
        foreach (DB::table('curriculum_nodes')
            ->where('academic_track_id', $trackId)
            ->where('status', 'published')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'code', 'type', 'title']) as $node) {
            $nodes[(string) $node->id] = $node;
        }

        return $nodes;
    }

    /** @param array<string, stdClass> $nodes */
    private function ancestor(string $nodeId, string $type, array $nodes): ?stdClass
    {
        $cursor = $nodeId;
        $visited = [];

        for ($depth = 0; $depth < 20; $depth++) {
            if (isset($visited[$cursor])) {
                return null;
            }
            $visited[$cursor] = true;
            $node = $nodes[$cursor] ?? null;
            if ($node === null) {
                return null;
            }
            if ((string) $node->type === $type) {
                return $node;
            }
            $parent = $node->parent_id;
            if (! is_string($parent) || $parent === '') {
                return null;
            }
            $cursor = $parent;
        }

        return null;
    }

    /**
     * @param array<string, array{id:string,reference:string,title:array<string,mixed>,scores:list<float>}> $buckets
     */
    private function bucket(array &$buckets, ?stdClass $node, float $score): void
    {
        if ($node === null) {
            return;
        }
        $id = (string) $node->id;
        if (! isset($buckets[$id])) {
            $buckets[$id] = [
                'id' => $id,
                'reference' => (string) $node->code,
                'title' => $this->decode((string) $node->title),
                'scores' => [],
            ];
        }
        $buckets[$id]['scores'][] = $score;
    }

    /**
     * @param array<string, array{id:string,reference:string,title:array<string,mixed>,scores:list<float>}> $buckets
     * @return list<array<string, mixed>>
     */
    private function summaries(array $buckets): array
    {
        ksort($buckets, SORT_STRING);
        $result = [];
        foreach ($buckets as $bucket) {
            $scores = $bucket['scores'];
            $result[] = [
                'id' => $bucket['id'],
                'reference' => $bucket['reference'],
                'title' => $bucket['title'],
                'skill_count' => count($scores),
                'average_mastery_percent' => $scores === []
                    ? null
                    : round(array_sum($scores) / count($scores), 2),
            ];
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function emptyActivity(): array
    {
        return [
            'attempts_started' => 0,
            'attempts_completed' => 0,
            'answered_questions' => 0,
            'graded_questions' => 0,
            'correct_questions' => 0,
            'accuracy_percent' => null,
            'practice_time_seconds' => 0,
            'active_days' => 0,
            'last_activity_at' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function emptyMastery(): array
    {
        return [
            'skills' => [],
            'topics' => [],
            'subjects' => [],
            'strong' => [],
            'attention' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function emptyRevisionAttention(): array
    {
        return [
            'algorithm_version' => SpacedRepetitionScheduler::ALGORITHM_VERSION,
            'due_count' => 0,
            'attention_count' => 0,
            'items' => [],
        ];
    }

    /**
     * @return array<string, mixed>|list<mixed>
     *
     * @throws JsonException
     */
    private function decode(string $json): array
    {
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}

<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use JsonException;

final class StudentAdaptiveStudyReadService
{
    private const MISSION_ITEM_LIMIT = 5;

    private const NEEDS_PRACTICE_LIMIT = 20;

    private const MISTAKE_ITEM_LIMIT = 100;

    public function __construct(
        private readonly AdaptiveSkillSelector $skillSelector,
        private readonly DailyStudyPlanComposer $planComposer,
        private readonly MasteryEngine $mastery,
        private readonly LearningOperationsService $operations,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(User $user): array
    {
        $context = DB::table('user_academic_contexts as contexts')
            ->join('academic_tracks as tracks', 'tracks.id', '=', 'contexts.academic_track_id')
            ->where('contexts.user_id', $user->getKey())
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
                'state' => 'onboarding_required',
                'features' => [
                    'daily_plan' => ['state' => 'disabled', 'effective' => false],
                    'mistake_notebook' => ['state' => 'disabled', 'effective' => false],
                ],
                'today_mission' => $this->disabledSurface('academic_context_required'),
                'needs_practice' => [
                    'status' => 'empty',
                    'algorithm_version' => AdaptiveSkillSelector::ALGORITHM_VERSION,
                    'items' => [],
                    'selection_fingerprint' => null,
                ],
                'mistakes' => $this->disabledSurface('academic_context_required'),
            ];
        }

        $contextData = [
            'context_id' => (string) $context->context_id,
            'academic_track_id' => (string) $context->academic_track_id,
            'track_reference' => (string) $context->track_reference,
            'year_level' => (string) $context->year_level,
            'track_title' => $this->decode((string) $context->track_title),
        ];

        $nodes = $this->publishedNodeMap((string) $context->academic_track_id);
        $features = $this->operations->features();
        $dailyPlan = $this->effectiveFeature($features['daily_plan'] ?? [], $user, $contextData);
        $mistakeNotebook = $this->effectiveFeature($features['mistake_notebook'] ?? [], $user, $contextData);

        $masteryStates = $this->masteryStates($user, (string) $context->context_id, $nodes);
        $selection = $this->skillSelector->select($masteryStates, self::NEEDS_PRACTICE_LIMIT);

        $needsPracticeItems = [];
        /** @var array<string, array<string, mixed>> $targetBySkill */
        $targetBySkill = [];
        foreach ($selection['selected'] as $selected) {
            $skillId = (string) $selected['skill_node_id'];
            $target = $this->studyTarget($skillId, $nodes);
            if ($target === null) {
                continue;
            }
            $targetBySkill[$skillId] = $target;
            $needsPracticeItems[] = $target + [
                'display_band' => $selected['display_band'],
                'score_percent' => $selected['score_percent'],
                'confidence' => $selected['confidence'],
                'evidence_count' => $selected['evidence_count'],
                'priority_reason' => $selected['priority_reason'],
            ];
        }

        $mistakeEvidence = $this->mistakes(
            $user,
            (string) $context->context_id,
            $nodes,
            $mistakeNotebook,
        );

        $mission = $dailyPlan['effective']
            ? $this->mission(
                $needsPracticeItems,
                $mistakeEvidence['items'],
                $nodes,
                $dailyPlan,
            )
            : $this->disabledSurface('feature_disabled');

        return [
            'state' => 'active',
            'context' => $contextData,
            'features' => [
                'daily_plan' => $dailyPlan['public'],
                'mistake_notebook' => $mistakeNotebook['public'],
            ],
            'today_mission' => $mission,
            'needs_practice' => [
                'status' => $needsPracticeItems === [] ? 'empty' : 'ready',
                'algorithm_version' => $selection['algorithm_version'],
                'items' => array_values($needsPracticeItems),
                'selection_fingerprint' => $selection['selection_fingerprint'],
            ],
            'mistakes' => $mistakeNotebook['effective']
                ? [
                    'status' => $mistakeEvidence['items'] === []
                        ? ($mistakeEvidence['skipped_count'] > 0 ? 'degraded' : 'empty')
                        : ($mistakeEvidence['skipped_count'] > 0 ? 'degraded' : 'ready'),
                    'algorithm_version' => 'latest-graded-answer-v1',
                    'items' => $mistakeEvidence['items'],
                    'skipped_evidence_count' => $mistakeEvidence['skipped_count'],
                ]
                : $this->disabledSurface('feature_disabled'),
        ];
    }

    /**
     * @param list<array<string, mixed>> $needsPractice
     * @param list<array<string, mixed>> $mistakes
     * @param array<string, object> $nodes
     * @param array<string, mixed> $dailyPlan
     * @return array<string, mixed>
     */
    private function mission(array $needsPractice, array $mistakes, array $nodes, array $dailyPlan): array
    {
        /** @var array<string, array<string, mixed>> $candidatesByTarget */
        $candidatesByTarget = [];
        /** @var array<string, array<string, mixed>> $metadataByTarget */
        $metadataByTarget = [];

        foreach ($mistakes as $mistake) {
            $skillId = (string) ($mistake['skill_id'] ?? '');
            $subjectReference = (string) ($mistake['subject']['reference'] ?? '');
            if ($skillId === ''
                || $this->subjectAllowed($dailyPlan, $subjectReference) === false) {
                continue;
            }

            $target = $this->studyTarget($skillId, $nodes);
            if ($target === null) {
                continue;
            }
            $targetKey = 'skill:'.$skillId;
            $candidatesByTarget[$targetKey] = [
                'target_key' => $targetKey,
                'source_type' => 'recent_mistake',
                'subject_id' => (string) $target['subject']['id'],
                'priority_score' => 100.0,
                'available_question_count' => (int) ($target['assessment']['available_question_count'] ?? 0),
            ];
            $metadataByTarget[$targetKey] = $target;
        }

        foreach ($needsPractice as $item) {
            $skillId = (string) ($item['skill_id'] ?? '');
            $subjectReference = (string) ($item['subject']['reference'] ?? '');
            if ($skillId === ''
                || $this->subjectAllowed($dailyPlan, $subjectReference) === false) {
                continue;
            }

            $targetKey = 'skill:'.$skillId;
            if (isset($candidatesByTarget[$targetKey])) {
                continue;
            }
            $candidatesByTarget[$targetKey] = [
                'target_key' => $targetKey,
                'source_type' => 'weak_skill',
                'subject_id' => (string) $item['subject']['id'],
                'priority_score' => max(0.0, 100.0 - (float) ($item['score_percent'] ?? 0.0)),
                'available_question_count' => (int) ($item['assessment']['available_question_count'] ?? 0),
            ];
            $metadataByTarget[$targetKey] = $item;
        }

        ksort($candidatesByTarget, SORT_STRING);
        $candidates = array_values($candidatesByTarget);
        $maxItems = min(self::MISSION_ITEM_LIMIT, max(1, count($candidates)));
        $plan = $this->planComposer->compose($candidates, $maxItems);

        $selected = [];
        foreach ($plan['selected'] as $item) {
            $targetKey = (string) $item['target_key'];
            $metadata = $metadataByTarget[$targetKey] ?? null;
            if ($metadata === null) {
                continue;
            }
            $selected[] = $metadata + [
                'source_type' => $item['source_type'],
                'reason' => $item['reason'],
                'priority_score' => $item['priority_score'],
            ];
        }

        $skipped = [];
        foreach ($plan['skipped_unavailable'] as $item) {
            $targetKey = (string) $item['target_key'];
            $metadata = $metadataByTarget[$targetKey] ?? null;
            $skipped[] = [
                'target_key' => $targetKey,
                'source_type' => $item['source_type'],
                'reason' => $item['reason'],
                'skill_id' => is_array($metadata) ? ($metadata['skill_id'] ?? null) : null,
            ];
        }

        return [
            'status' => $plan['status'],
            'algorithm_version' => $plan['algorithm_version'],
            'requested_max_items' => $plan['requested_max_items'],
            'input_candidate_count' => $plan['input_candidate_count'],
            'available_candidate_count' => $plan['available_candidate_count'],
            'selected_count' => count($selected),
            'shortfall_count' => $plan['shortfall_count'],
            'selected' => $selected,
            'skipped_unavailable' => $skipped,
            'plan_fingerprint' => $plan['plan_fingerprint'],
        ];
    }

    /**
     * @param array<string, object> $nodes
     * @return list<array<string, mixed>>
     */
    private function masteryStates(User $user, string $contextId, array $nodes): array
    {
        $states = [];
        $rows = DB::table('student_skill_mastery_states')
            ->where('user_id', $user->getKey())
            ->where('academic_context_id', $contextId)
            ->whereNull('archived_at')
            ->where('evidence_count', '>', 0)
            ->orderBy('skill_node_id')
            ->get(['skill_node_id']);

        foreach ($rows as $row) {
            $skillId = (string) $row->skill_node_id;
            if ($this->isPublishedSkillWithSubject($skillId, $nodes) === false) {
                continue;
            }

            $state = $this->mastery->state($user, $contextId, $skillId);
            $states[] = [
                'skill_node_id' => $skillId,
                'display_band' => (string) $state['display_band'],
                'score_percent' => (float) $state['score_percent'],
                'confidence' => (float) $state['confidence'],
                'evidence_count' => (int) $state['evidence_count'],
            ];
        }

        return $states;
    }

    /**
     * @param array<string, object> $nodes
     * @param array<string, mixed> $mistakeNotebook
     * @return array{items:list<array<string, mixed>>,skipped_count:int}
     */
    private function mistakes(User $user, string $contextId, array $nodes, array $mistakeNotebook): array
    {
        if ($mistakeNotebook['effective'] === false) {
            return ['items' => [], 'skipped_count' => 0];
        }

        $rows = DB::table('attempt_answers as answers')
            ->join('attempt_questions as questions', 'questions.id', '=', 'answers.attempt_question_id')
            ->join('attempts', 'attempts.id', '=', 'questions.attempt_id')
            ->where('attempts.user_id', $user->getKey())
            ->where('attempts.academic_context_id', $contextId)
            ->where('attempts.status', 'graded')
            ->whereNull('attempts.archived_at')
            ->whereNotNull('answers.is_correct')
            ->whereNotNull('answers.graded_at')
            ->orderBy('questions.id')
            ->orderBy('answers.revision')
            ->get([
                'questions.id as attempt_question_id',
                'questions.question_snapshot',
                'answers.revision',
                'answers.is_correct',
                'answers.graded_at',
            ]);

        /** @var array<string, object> $latest */
        $latest = [];
        foreach ($rows as $row) {
            $latest[(string) $row->attempt_question_id] = $row;
        }

        $items = [];
        $skipped = 0;
        foreach ($latest as $row) {
            if ((bool) $row->is_correct) {
                continue;
            }

            try {
                $snapshot = $this->decode((string) $row->question_snapshot);
            } catch (JsonException) {
                $skipped++;

                continue;
            }

            $skillId = $snapshot['skill_node_id'] ?? null;
            if (is_string($skillId) === false || $this->isPublishedSkillWithSubject($skillId, $nodes) === false) {
                $skipped++;

                continue;
            }

            $target = $this->studyTarget($skillId, $nodes);
            if ($target === null) {
                $skipped++;

                continue;
            }

            $subjectReference = (string) ($target['subject']['reference'] ?? '');
            if ($this->subjectAllowed($mistakeNotebook, $subjectReference) === false) {
                continue;
            }

            $prompt = $snapshot['prompt'] ?? [];
            if (! is_array($prompt)) {
                $prompt = [];
            }

            $items[] = $target + [
                'attempt_question_id' => (string) $row->attempt_question_id,
                'revision' => (int) $row->revision,
                'prompt' => $prompt,
                'latest_wrong_at' => CarbonImmutable::parse((string) $row->graded_at)->toIso8601String(),
                'reason' => 'recent_mistake',
            ];
        }

        usort($items, static function (array $left, array $right): int {
            $time = strcmp((string) $right['latest_wrong_at'], (string) $left['latest_wrong_at']);

            return $time !== 0
                ? $time
                : strcmp((string) $left['attempt_question_id'], (string) $right['attempt_question_id']);
        });

        return [
            'items' => array_values(array_slice($items, 0, self::MISTAKE_ITEM_LIMIT)),
            'skipped_count' => $skipped,
        ];
    }

    /**
     * @param array<string, object> $nodes
     * @return array<string, mixed>|null
     */
    private function studyTarget(string $skillId, array $nodes): ?array
    {
        $skill = $nodes[$skillId] ?? null;
        if ($skill === null || (string) $skill->type !== 'skill') {
            return null;
        }

        $subject = $this->subjectFor($skillId, $nodes);
        if ($subject === null) {
            return null;
        }

        $assessment = $this->assessmentForSkill($skillId);

        return [
            'skill_id' => $skillId,
            'skill_reference' => (string) $skill->code,
            'skill_title' => $this->decode((string) $skill->title),
            'subject' => [
                'id' => (string) $subject->id,
                'reference' => (string) $subject->code,
                'title' => $this->decode((string) $subject->title),
            ],
            'assessment' => $assessment,
        ];
    }

    /** @return array<string, mixed>|null */
    private function assessmentForSkill(string $skillId): ?array
    {
        $rows = DB::table('quizzes as quizzes')
            ->join('quiz_questions as links', 'links.quiz_id', '=', 'quizzes.id')
            ->join('questions as questions', 'questions.id', '=', 'links.question_id')
            ->join('curriculum_nodes as question_nodes', 'question_nodes.id', '=', 'questions.curriculum_node_id')
            ->leftJoin('learning_objectives as objectives', 'objectives.id', '=', 'questions.learning_objective_id')
            ->where('quizzes.status', 'published')
            ->whereIn('quizzes.kind', ['practice', 'quiz'])
            ->where('questions.status', 'published')
            ->where('questions.review_state', 'approved')
            ->where(function ($query) use ($skillId): void {
                $query->where('objectives.skill_node_id', $skillId)
                    ->orWhere(function ($nested) use ($skillId): void {
                        $nested->where('questions.curriculum_node_id', $skillId)
                            ->where('question_nodes.type', 'skill');
                    });
            })
            ->orderBy('quizzes.kind')
            ->orderBy('quizzes.id')
            ->orderBy('links.source_position')
            ->get([
                'quizzes.id',
                'quizzes.kind',
                'quizzes.title',
                'questions.id as question_id',
            ]);

        if ($rows->isEmpty()) {
            return [
                'id' => null,
                'kind' => null,
                'title' => null,
                'available_question_count' => 0,
            ];
        }

        $firstQuizId = (string) $rows->first()->id;
        $questionIds = [];
        $first = null;
        foreach ($rows as $row) {
            if ((string) $row->id !== $firstQuizId) {
                break;
            }
            $first ??= $row;
            $questionIds[(string) $row->question_id] = true;
        }

        return [
            'id' => $firstQuizId,
            'kind' => (string) $first->kind,
            'title' => $this->decode((string) $first->title),
            'available_question_count' => count($questionIds),
        ];
    }

    /** @return array<string, object> */
    private function publishedNodeMap(string $trackId): array
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

    /** @param array<string, object> $nodes */
    private function isPublishedSkillWithSubject(string $skillId, array $nodes): bool
    {
        return isset($nodes[$skillId])
            && (string) $nodes[$skillId]->type === 'skill'
            && $this->subjectFor($skillId, $nodes) !== null;
    }

    /**
     * @param array<string, object> $nodes
     */
    private function subjectFor(string $nodeId, array $nodes): ?object
    {
        $visited = [];
        $cursor = $nodeId;

        for ($depth = 0; $depth < 20; $depth++) {
            if (isset($visited[$cursor])) {
                return null;
            }
            $visited[$cursor] = true;

            $node = $nodes[$cursor] ?? null;
            if ($node === null) {
                return null;
            }
            if ((string) $node->type === 'subject') {
                return $node;
            }

            $parentId = $node->parent_id;
            if (is_string($parentId) === false || $parentId === '') {
                return null;
            }
            $cursor = $parentId;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $feature
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function effectiveFeature(array $feature, User $user, array $context): array
    {
        $state = is_string($feature['state'] ?? null) ? (string) $feature['state'] : 'disabled';
        $scope = is_array($feature['scope'] ?? null) ? $feature['scope'] : null;
        $effective = match ($state) {
            'enabled' => true,
            'pilot' => $scope !== null && $this->contextScopeMatches($scope, $user, $context),
            default => false,
        };

        return [
            'state' => $state,
            'scope' => $scope,
            'effective' => $effective,
            'public' => ['state' => $state, 'effective' => $effective],
        ];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $context
     */
    private function contextScopeMatches(array $scope, User $user, array $context): bool
    {
        $userIds = $scope['user_ids'] ?? null;
        if (is_array($userIds) && $userIds !== []
            && in_array((string) $user->getKey(), $userIds, true) === false) {
            return false;
        }

        $years = $scope['academic_years'] ?? null;
        if (is_array($years) && $years !== []
            && in_array((string) $context['year_level'], $years, true) === false) {
            return false;
        }

        return true;
    }

    /** @param array<string, mixed> $feature */
    private function subjectAllowed(array $feature, string $subjectReference): bool
    {
        if ($feature['effective'] === false) {
            return false;
        }

        $scope = $feature['scope'] ?? null;
        if (is_array($scope) === false) {
            return true;
        }

        $subjects = $scope['subject_codes'] ?? null;

        return is_array($subjects) === false
            || $subjects === []
            || in_array($subjectReference, $subjects, true);
    }

    /** @return array<string, mixed> */
    private function disabledSurface(string $reason): array
    {
        return [
            'status' => 'disabled',
            'reason' => $reason,
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
        /** @var array<string, mixed>|list<mixed> $decoded */
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }
}

<?php

namespace App\Services;

use InvalidArgumentException;
use JsonException;

final class DailyStudyPlanComposer
{
    public const ALGORITHM_VERSION = 'daily-study-plan-v1';

    /** @var array<string, int> */
    private const SOURCE_PRIORITY = [
        'due_revision' => 0,
        'recent_mistake' => 1,
        'weak_skill' => 2,
    ];

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return array{
     *   algorithm_version:string,
     *   status:string,
     *   requested_max_items:int,
     *   input_candidate_count:int,
     *   available_candidate_count:int,
     *   selected_count:int,
     *   shortfall_count:int,
     *   selected:list<array{
     *     target_key:string,
     *     source_type:string,
     *     subject_id:string,
     *     priority_score:float,
     *     available_question_count:int,
     *     reason:string
     *   }>,
     *   skipped_unavailable:list<array{
     *     target_key:string,
     *     source_type:string,
     *     subject_id:string,
     *     reason:string
     *   }>,
     *   plan_fingerprint:string
     * }
     *
     * @throws JsonException
     */
    public function compose(array $candidates, int $maxItems): array
    {
        if ($maxItems < 1 || $maxItems > 50) {
            throw new InvalidArgumentException('Daily Plan item limit must be between 1 and 50.');
        }

        $seenTargets = [];
        /** @var list<array{target_key:string,source_type:string,subject_id:string,priority_score:float,available_question_count:int,reason:string}> $available */
        $available = [];
        /** @var list<array{target_key:string,source_type:string,subject_id:string,reason:string}> $skippedUnavailable */
        $skippedUnavailable = [];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalize($candidate);
            $targetKey = $normalized['target_key'];

            if (isset($seenTargets[$targetKey])) {
                throw new InvalidArgumentException('Duplicate or contradictory Daily Plan target.');
            }
            $seenTargets[$targetKey] = true;

            if ($normalized['available_question_count'] === 0) {
                $skippedUnavailable[] = [
                    'target_key' => $targetKey,
                    'source_type' => $normalized['source_type'],
                    'subject_id' => $normalized['subject_id'],
                    'reason' => 'question_unavailable',
                ];

                continue;
            }

            $available[] = $normalized + [
                'reason' => $normalized['source_type'],
            ];
        }

        usort(
            $skippedUnavailable,
            static fn (array $left, array $right): int => strcmp($left['target_key'], $right['target_key']),
        );

        /** @var array<string, list<array{target_key:string,source_type:string,subject_id:string,priority_score:float,available_question_count:int,reason:string}>> $subjectQueues */
        $subjectQueues = [];
        foreach ($available as $candidate) {
            $subjectQueues[$candidate['subject_id']][] = $candidate;
        }

        foreach ($subjectQueues as &$queue) {
            usort($queue, fn (array $left, array $right): int => $this->compare($left, $right));
        }
        unset($queue);

        /** @var list<array{target_key:string,source_type:string,subject_id:string,priority_score:float,available_question_count:int,reason:string}> $selected */
        $selected = [];

        while ($subjectQueues !== [] && count($selected) < $maxItems) {
            $activeSubjects = array_keys($subjectQueues);
            usort($activeSubjects, function (string $leftSubject, string $rightSubject) use ($subjectQueues): int {
                $leftHead = $subjectQueues[$leftSubject][0];
                $rightHead = $subjectQueues[$rightSubject][0];
                $priority = $this->compare($leftHead, $rightHead);

                return $priority !== 0 ? $priority : strcmp($leftSubject, $rightSubject);
            });

            foreach ($activeSubjects as $subjectId) {
                if (count($selected) >= $maxItems) {
                    break;
                }

                $selected[] = $subjectQueues[$subjectId][0];
                array_shift($subjectQueues[$subjectId]);

                if ($subjectQueues[$subjectId] === []) {
                    unset($subjectQueues[$subjectId]);
                }
            }
        }

        $shortfall = max(0, $maxItems - count($selected));
        $status = match (true) {
            $selected === [] => 'empty',
            $shortfall > 0 || $skippedUnavailable !== [] => 'degraded',
            default => 'ready',
        };

        $fingerprintPayload = [
            'algorithm_version' => self::ALGORITHM_VERSION,
            'requested_max_items' => $maxItems,
            'selected' => $selected,
            'skipped_unavailable' => $skippedUnavailable,
        ];

        return [
            'algorithm_version' => self::ALGORITHM_VERSION,
            'status' => $status,
            'requested_max_items' => $maxItems,
            'input_candidate_count' => count($candidates),
            'available_candidate_count' => count($available),
            'selected_count' => count($selected),
            'shortfall_count' => $shortfall,
            'selected' => $selected,
            'skipped_unavailable' => $skippedUnavailable,
            'plan_fingerprint' => hash('sha256', json_encode($fingerprintPayload, JSON_THROW_ON_ERROR)),
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array{
     *   target_key:string,
     *   source_type:string,
     *   subject_id:string,
     *   priority_score:float,
     *   available_question_count:int
     * }
     */
    private function normalize(array $candidate): array
    {
        $targetKey = $candidate['target_key'] ?? null;
        $sourceType = $candidate['source_type'] ?? null;
        $subjectId = $candidate['subject_id'] ?? null;
        $priorityScore = $candidate['priority_score'] ?? null;
        $availableQuestionCount = $candidate['available_question_count'] ?? null;

        if (! is_string($targetKey) || trim($targetKey) === '') {
            throw new InvalidArgumentException('Daily Plan candidate requires a target key.');
        }

        if (! is_string($sourceType) || ! array_key_exists($sourceType, self::SOURCE_PRIORITY)) {
            throw new InvalidArgumentException('Daily Plan candidate contains an unknown source type.');
        }

        if (! is_string($subjectId) || trim($subjectId) === '') {
            throw new InvalidArgumentException('Daily Plan candidate requires a subject identifier.');
        }

        if ((! is_int($priorityScore) && ! is_float($priorityScore))
            || $priorityScore < 0
            || $priorityScore > 100) {
            throw new InvalidArgumentException('Daily Plan priority score must be between 0 and 100.');
        }

        if (! is_int($availableQuestionCount) || $availableQuestionCount < 0) {
            throw new InvalidArgumentException('Available question count must be a non-negative integer.');
        }

        return [
            'target_key' => $targetKey,
            'source_type' => $sourceType,
            'subject_id' => $subjectId,
            'priority_score' => (float) $priorityScore,
            'available_question_count' => $availableQuestionCount,
        ];
    }

    /**
     * @param array{
     *   target_key:string,
     *   source_type:string,
     *   subject_id:string,
     *   priority_score:float,
     *   available_question_count:int,
     *   reason:string
     * } $left
     * @param array{
     *   target_key:string,
     *   source_type:string,
     *   subject_id:string,
     *   priority_score:float,
     *   available_question_count:int,
     *   reason:string
     * } $right
     */
    private function compare(array $left, array $right): int
    {
        $source = self::SOURCE_PRIORITY[$left['source_type']] <=> self::SOURCE_PRIORITY[$right['source_type']];
        if ($source !== 0) {
            return $source;
        }

        $priority = $right['priority_score'] <=> $left['priority_score'];
        if ($priority !== 0) {
            return $priority;
        }

        return strcmp($left['target_key'], $right['target_key']);
    }
}

<?php

namespace App\Services;

use InvalidArgumentException;
use JsonException;

final class DiagnosticBaselineInterpreter
{
    public const ALGORITHM_VERSION = 'diagnostic-baseline-v1';

    /**
     * @param  list<array<string, mixed>>  $skillResults
     * @return array{
     *   algorithm_version:string,
     *   input_skill_count:int,
     *   tested_skill_count:int,
     *   untested_skill_count:int,
     *   tested:list<array{
     *     skill_node_id:string,
     *     answered_count:int,
     *     correct_count:int,
     *     accuracy_percent:float,
     *     evidence_state:string
     *   }>,
     *   untested:list<array{
     *     skill_node_id:string,
     *     evidence_state:string
     *   }>,
     *   baseline_fingerprint:string
     * }
     *
     * @throws JsonException
     */
    public function interpret(array $skillResults): array
    {
        $seen = [];
        $tested = [];
        $untested = [];

        foreach ($skillResults as $result) {
            $normalized = $this->normalize($result);
            $skillId = $normalized['skill_node_id'];

            if (isset($seen[$skillId])) {
                throw new InvalidArgumentException('Duplicate diagnostic result for Skill.');
            }
            $seen[$skillId] = true;

            if ($normalized['answered_count'] === 0) {
                $untested[] = [
                    'skill_node_id' => $skillId,
                    'evidence_state' => 'untested',
                ];

                continue;
            }

            $tested[] = [
                'skill_node_id' => $skillId,
                'answered_count' => $normalized['answered_count'],
                'correct_count' => $normalized['correct_count'],
                'accuracy_percent' => round(
                    ($normalized['correct_count'] / $normalized['answered_count']) * 100,
                    2,
                ),
                'evidence_state' => 'diagnostic_evidence',
            ];
        }

        usort(
            $tested,
            static fn (array $left, array $right): int => strcmp($left['skill_node_id'], $right['skill_node_id']),
        );
        usort(
            $untested,
            static fn (array $left, array $right): int => strcmp($left['skill_node_id'], $right['skill_node_id']),
        );

        $fingerprintPayload = [
            'algorithm_version' => self::ALGORITHM_VERSION,
            'tested' => $tested,
            'untested' => $untested,
        ];

        return [
            'algorithm_version' => self::ALGORITHM_VERSION,
            'input_skill_count' => count($skillResults),
            'tested_skill_count' => count($tested),
            'untested_skill_count' => count($untested),
            'tested' => $tested,
            'untested' => $untested,
            'baseline_fingerprint' => hash('sha256', json_encode($fingerprintPayload, JSON_THROW_ON_ERROR)),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{skill_node_id:string, answered_count:int, correct_count:int}
     */
    private function normalize(array $result): array
    {
        $skillId = $result['skill_node_id'] ?? null;
        $answeredCount = $result['answered_count'] ?? null;
        $correctCount = $result['correct_count'] ?? null;

        if (! is_string($skillId) || trim($skillId) === '') {
            throw new InvalidArgumentException('Diagnostic result requires a Skill identifier.');
        }

        if (! is_int($answeredCount) || $answeredCount < 0) {
            throw new InvalidArgumentException('Diagnostic answered count must be a non-negative integer.');
        }

        if (! is_int($correctCount) || $correctCount < 0 || $correctCount > $answeredCount) {
            throw new InvalidArgumentException('Diagnostic correct count must be between zero and answered count.');
        }

        return [
            'skill_node_id' => $skillId,
            'answered_count' => $answeredCount,
            'correct_count' => $correctCount,
        ];
    }
}

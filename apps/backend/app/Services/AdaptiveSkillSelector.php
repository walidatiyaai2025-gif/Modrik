<?php

namespace App\Services;

use InvalidArgumentException;
use JsonException;

final class AdaptiveSkillSelector
{
    public const ALGORITHM_VERSION = 'weak-skill-selector-v1';

    /** @var array<string, int> */
    private const BAND_PRIORITY = [
        'critical' => 0,
        'weak' => 1,
        'developing' => 2,
        'strong' => 3,
    ];

    /**
     * @param  list<array<string, mixed>>  $masteryStates
     * @return array{
     *   algorithm_version:string,
     *   input_count:int,
     *   candidate_count:int,
     *   limit:int,
     *   selected:list<array{
     *     skill_node_id:string,
     *     display_band:string,
     *     score_percent:float,
     *     confidence:float,
     *     evidence_count:int,
     *     priority_reason:string
     *   }>,
     *   selection_fingerprint:string
     * }
     *
     * @throws JsonException
     */
    public function select(array $masteryStates, int $limit = 5): array
    {
        if ($limit < 1 || $limit > 50) {
            throw new InvalidArgumentException('Adaptive skill selection limit must be between 1 and 50.');
        }

        $seen = [];
        $candidates = [];

        foreach ($masteryStates as $state) {
            $normalized = $this->normalize($state);
            $skillId = $normalized['skill_node_id'];

            if (isset($seen[$skillId])) {
                throw new InvalidArgumentException('Duplicate authoritative mastery state for Skill.');
            }
            $seen[$skillId] = true;

            if ($normalized['evidence_count'] === 0) {
                continue;
            }

            if (! in_array($normalized['display_band'], ['critical', 'weak'], true)) {
                continue;
            }

            $candidates[] = $normalized + [
                'priority_reason' => $normalized['display_band'].'_mastery',
            ];
        }

        usort($candidates, function (array $left, array $right): int {
            $band = self::BAND_PRIORITY[$left['display_band']] <=> self::BAND_PRIORITY[$right['display_band']];
            if ($band !== 0) {
                return $band;
            }

            $score = $left['score_percent'] <=> $right['score_percent'];
            if ($score !== 0) {
                return $score;
            }

            $confidence = $right['confidence'] <=> $left['confidence'];
            if ($confidence !== 0) {
                return $confidence;
            }

            $evidence = $right['evidence_count'] <=> $left['evidence_count'];
            if ($evidence !== 0) {
                return $evidence;
            }

            return strcmp($left['skill_node_id'], $right['skill_node_id']);
        });

        $selected = array_values(array_slice($candidates, 0, $limit));
        $fingerprintPayload = [
            'algorithm_version' => self::ALGORITHM_VERSION,
            'limit' => $limit,
            'selected' => $selected,
        ];

        return [
            'algorithm_version' => self::ALGORITHM_VERSION,
            'input_count' => count($masteryStates),
            'candidate_count' => count($candidates),
            'limit' => $limit,
            'selected' => $selected,
            'selection_fingerprint' => hash('sha256', json_encode($fingerprintPayload, JSON_THROW_ON_ERROR)),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{
     *   skill_node_id:string,
     *   display_band:string,
     *   score_percent:float,
     *   confidence:float,
     *   evidence_count:int
     * }
     */
    private function normalize(array $state): array
    {
        $skillId = $state['skill_node_id'] ?? null;
        $band = $state['display_band'] ?? null;
        $score = $state['score_percent'] ?? null;
        $confidence = $state['confidence'] ?? null;
        $evidenceCount = $state['evidence_count'] ?? null;

        if (! is_string($skillId) || trim($skillId) === '') {
            throw new InvalidArgumentException('Authoritative mastery state requires a Skill identifier.');
        }

        if (! is_string($band) || ! array_key_exists($band, self::BAND_PRIORITY)) {
            throw new InvalidArgumentException('Authoritative mastery state contains an unknown display band.');
        }

        if ((! is_int($score) && ! is_float($score)) || $score < 0 || $score > 100) {
            throw new InvalidArgumentException('Authoritative mastery score must be between 0 and 100.');
        }

        if ((! is_int($confidence) && ! is_float($confidence)) || $confidence < 0 || $confidence > 1) {
            throw new InvalidArgumentException('Authoritative mastery confidence must be between 0 and 1.');
        }

        if (! is_int($evidenceCount) || $evidenceCount < 0) {
            throw new InvalidArgumentException('Authoritative mastery evidence count must be a non-negative integer.');
        }

        return [
            'skill_node_id' => $skillId,
            'display_band' => $band,
            'score_percent' => (float) $score,
            'confidence' => (float) $confidence,
            'evidence_count' => $evidenceCount,
        ];
    }
}

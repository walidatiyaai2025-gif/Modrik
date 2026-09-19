<?php

namespace Tests\Unit;

use App\Services\AdaptiveSkillSelector;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AdaptiveSkillSelectorTest extends TestCase
{
    public function test_selection_is_input_order_independent_and_prioritizes_evidenced_critical_then_weak_skills(): void
    {
        $selector = new AdaptiveSkillSelector;
        $states = [
            $this->state('skill-weak', 'weak', 45.0, 0.8, 5),
            $this->state('skill-critical-b', 'critical', 20.0, 0.7, 4),
            $this->state('skill-strong', 'strong', 90.0, 0.9, 8),
            $this->state('skill-critical-a', 'critical', 20.0, 0.9, 3),
            $this->state('skill-developing', 'developing', 70.0, 0.9, 8),
        ];

        $first = $selector->select($states, 3);
        $second = $selector->select(array_reverse($states), 3);

        self::assertSame($first['selected'], $second['selected']);
        self::assertSame($first['selection_fingerprint'], $second['selection_fingerprint']);
        self::assertSame(3, $first['candidate_count']);
        self::assertSame(
            ['skill-critical-a', 'skill-critical-b', 'skill-weak'],
            array_column($first['selected'], 'skill_node_id'),
        );
    }

    public function test_untested_cold_start_skill_is_not_selected_as_fake_weakness(): void
    {
        $selector = new AdaptiveSkillSelector;

        $result = $selector->select([
            $this->state('untested-critical', 'critical', 0.0, 0.0, 0),
            $this->state('evidenced-weak', 'weak', 50.0, 0.6, 2),
        ]);

        self::assertSame(1, $result['candidate_count']);
        self::assertSame(['evidenced-weak'], array_column($result['selected'], 'skill_node_id'));
    }

    public function test_limit_is_applied_after_deterministic_priority_sorting(): void
    {
        $selector = new AdaptiveSkillSelector;

        $result = $selector->select([
            $this->state('weak-low', 'weak', 40.0, 0.9, 5),
            $this->state('critical-high', 'critical', 35.0, 0.9, 5),
            $this->state('critical-low', 'critical', 10.0, 0.4, 2),
        ], 2);

        self::assertSame(
            ['critical-low', 'critical-high'],
            array_column($result['selected'], 'skill_node_id'),
        );
    }

    public function test_duplicate_skill_state_fails_closed(): void
    {
        $selector = new AdaptiveSkillSelector;

        $this->expectException(InvalidArgumentException::class);

        $selector->select([
            $this->state('duplicate', 'critical', 10.0, 0.8, 3),
            $this->state('duplicate', 'weak', 40.0, 0.8, 3),
        ]);
    }

    public function test_invalid_limit_fails_closed(): void
    {
        $selector = new AdaptiveSkillSelector;

        $this->expectException(InvalidArgumentException::class);

        $selector->select([], 0);
    }

    public function test_malformed_authoritative_state_fails_closed(): void
    {
        $selector = new AdaptiveSkillSelector;

        $this->expectException(InvalidArgumentException::class);

        $selector->select([[
            'skill_node_id' => 'skill-bad',
            'display_band' => 'critical',
            'score_percent' => 101,
            'confidence' => 0.5,
            'evidence_count' => 1,
        ]]);
    }

    /** @return array<string, mixed> */
    private function state(
        string $skillId,
        string $band,
        float $score,
        float $confidence,
        int $evidenceCount,
    ): array {
        return [
            'skill_node_id' => $skillId,
            'display_band' => $band,
            'score_percent' => $score,
            'confidence' => $confidence,
            'evidence_count' => $evidenceCount,
        ];
    }
}

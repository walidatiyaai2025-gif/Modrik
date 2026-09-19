<?php

namespace Tests\Unit;

use App\Services\DiagnosticBaselineInterpreter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DiagnosticBaselineInterpreterTest extends TestCase
{
    public function test_tested_skills_receive_diagnostic_evidence_without_mastery_assignment(): void
    {
        $interpreter = new DiagnosticBaselineInterpreter;

        $result = $interpreter->interpret([
            ['skill_node_id' => 'skill-b', 'answered_count' => 4, 'correct_count' => 3],
            ['skill_node_id' => 'skill-a', 'answered_count' => 2, 'correct_count' => 1],
        ]);

        self::assertSame(DiagnosticBaselineInterpreter::ALGORITHM_VERSION, $result['algorithm_version']);
        self::assertSame(2, $result['tested_skill_count']);
        self::assertSame(0, $result['untested_skill_count']);
        self::assertSame(['skill-a', 'skill-b'], array_column($result['tested'], 'skill_node_id'));
        self::assertSame([50.0, 75.0], array_column($result['tested'], 'accuracy_percent'));
        self::assertSame(
            ['diagnostic_evidence', 'diagnostic_evidence'],
            array_column($result['tested'], 'evidence_state'),
        );
        self::assertArrayNotHasKey('mastery_score', $result['tested'][0]);
        self::assertArrayNotHasKey('mastery_band', $result['tested'][0]);
    }

    public function test_untested_skills_remain_explicitly_untested_without_fake_zero_mastery(): void
    {
        $interpreter = new DiagnosticBaselineInterpreter;

        $result = $interpreter->interpret([
            ['skill_node_id' => 'skill-tested', 'answered_count' => 1, 'correct_count' => 0],
            ['skill_node_id' => 'skill-untested', 'answered_count' => 0, 'correct_count' => 0],
        ]);

        self::assertSame(1, $result['tested_skill_count']);
        self::assertSame(1, $result['untested_skill_count']);
        self::assertSame('skill-untested', $result['untested'][0]['skill_node_id']);
        self::assertSame('untested', $result['untested'][0]['evidence_state']);
        self::assertArrayNotHasKey('accuracy_percent', $result['untested'][0]);
        self::assertArrayNotHasKey('mastery_score', $result['untested'][0]);
    }

    public function test_interpretation_is_independent_of_input_order(): void
    {
        $interpreter = new DiagnosticBaselineInterpreter;
        $input = [
            ['skill_node_id' => 'skill-c', 'answered_count' => 0, 'correct_count' => 0],
            ['skill_node_id' => 'skill-b', 'answered_count' => 3, 'correct_count' => 2],
            ['skill_node_id' => 'skill-a', 'answered_count' => 2, 'correct_count' => 2],
        ];

        $first = $interpreter->interpret($input);
        $second = $interpreter->interpret(array_reverse($input));

        self::assertSame($first['tested'], $second['tested']);
        self::assertSame($first['untested'], $second['untested']);
        self::assertSame($first['baseline_fingerprint'], $second['baseline_fingerprint']);
    }

    public function test_duplicate_skill_result_fails_closed(): void
    {
        $interpreter = new DiagnosticBaselineInterpreter;

        $this->expectException(InvalidArgumentException::class);

        $interpreter->interpret([
            ['skill_node_id' => 'duplicate', 'answered_count' => 1, 'correct_count' => 1],
            ['skill_node_id' => 'duplicate', 'answered_count' => 1, 'correct_count' => 0],
        ]);
    }

    public function test_correct_count_above_answered_count_fails_closed(): void
    {
        $interpreter = new DiagnosticBaselineInterpreter;

        $this->expectException(InvalidArgumentException::class);

        $interpreter->interpret([
            ['skill_node_id' => 'invalid', 'answered_count' => 1, 'correct_count' => 2],
        ]);
    }
}

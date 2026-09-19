<?php

namespace Tests\Unit;

use App\Services\DailyStudyPlanComposer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DailyStudyPlanComposerTest extends TestCase
{
    public function test_plan_is_input_order_independent_and_balances_subjects(): void
    {
        $composer = new DailyStudyPlanComposer;
        $candidates = [
            $this->candidate('math-due', 'due_revision', 'math', 95.0, 8),
            $this->candidate('math-weak', 'weak_skill', 'math', 90.0, 6),
            $this->candidate('english-mistake', 'recent_mistake', 'english', 80.0, 5),
            $this->candidate('english-weak', 'weak_skill', 'english', 70.0, 5),
        ];

        $first = $composer->compose($candidates, 4);
        $second = $composer->compose(array_reverse($candidates), 4);

        self::assertSame($first['selected'], $second['selected']);
        self::assertSame($first['plan_fingerprint'], $second['plan_fingerprint']);
        self::assertSame('ready', $first['status']);
        self::assertSame(
            ['math-due', 'english-mistake', 'math-weak', 'english-weak'],
            array_column($first['selected'], 'target_key'),
        );
    }

    public function test_unavailable_questions_are_skipped_and_reported_as_degraded(): void
    {
        $composer = new DailyStudyPlanComposer;

        $result = $composer->compose([
            $this->candidate('revision-no-content', 'due_revision', 'math', 100.0, 0),
            $this->candidate('mistake-with-content', 'recent_mistake', 'math', 80.0, 2),
        ], 2);

        self::assertSame('degraded', $result['status']);
        self::assertSame(1, $result['available_candidate_count']);
        self::assertSame(1, $result['selected_count']);
        self::assertSame(1, $result['shortfall_count']);
        self::assertSame('revision-no-content', $result['skipped_unavailable'][0]['target_key']);
        self::assertSame('question_unavailable', $result['skipped_unavailable'][0]['reason']);
    }

    public function test_all_unavailable_content_returns_truthful_empty_plan(): void
    {
        $composer = new DailyStudyPlanComposer;

        $result = $composer->compose([
            $this->candidate('weak-a', 'weak_skill', 'science', 80.0, 0),
            $this->candidate('due-b', 'due_revision', 'math', 90.0, 0),
        ], 3);

        self::assertSame('empty', $result['status']);
        self::assertSame(0, $result['selected_count']);
        self::assertSame(3, $result['shortfall_count']);
        self::assertCount(2, $result['skipped_unavailable']);
    }

    public function test_study_limit_is_applied_after_priority_and_subject_balance(): void
    {
        $composer = new DailyStudyPlanComposer;

        $result = $composer->compose([
            $this->candidate('math-due', 'due_revision', 'math', 90.0, 4),
            $this->candidate('science-due', 'due_revision', 'science', 80.0, 4),
            $this->candidate('english-mistake', 'recent_mistake', 'english', 100.0, 4),
        ], 2);

        self::assertSame(2, $result['selected_count']);
        self::assertSame(
            ['math-due', 'science-due'],
            array_column($result['selected'], 'target_key'),
        );
    }

    public function test_duplicate_target_fails_closed(): void
    {
        $composer = new DailyStudyPlanComposer;

        $this->expectException(InvalidArgumentException::class);

        $composer->compose([
            $this->candidate('same-target', 'due_revision', 'math', 90.0, 3),
            $this->candidate('same-target', 'weak_skill', 'math', 80.0, 3),
        ], 2);
    }

    public function test_invalid_study_limit_fails_closed(): void
    {
        $composer = new DailyStudyPlanComposer;

        $this->expectException(InvalidArgumentException::class);

        $composer->compose([], 0);
    }

    /** @return array<string, mixed> */
    private function candidate(
        string $targetKey,
        string $sourceType,
        string $subjectId,
        float $priorityScore,
        int $availableQuestionCount,
    ): array {
        return [
            'target_key' => $targetKey,
            'source_type' => $sourceType,
            'subject_id' => $subjectId,
            'priority_score' => $priorityScore,
            'available_question_count' => $availableQuestionCount,
        ];
    }
}

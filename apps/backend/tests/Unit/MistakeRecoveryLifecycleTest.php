<?php

namespace Tests\Unit;

use App\Services\MistakeRecoveryLifecycle;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MistakeRecoveryLifecycleTest extends TestCase
{
    public function test_canonical_recovery_path_is_deterministic(): void
    {
        $lifecycle = new MistakeRecoveryLifecycle;

        $first = $lifecycle->transition(
            MistakeRecoveryLifecycle::STATE_NEW,
            MistakeRecoveryLifecycle::EVENT_BEGIN_RELEARNING,
        );
        self::assertSame(MistakeRecoveryLifecycle::STATE_RELEARNING, $first['next_state']);
        self::assertTrue($first['changed']);

        $reviewDue = $lifecycle->transition(
            $first['next_state'],
            MistakeRecoveryLifecycle::EVENT_SCHEDULE_REVIEW,
        );
        self::assertSame(MistakeRecoveryLifecycle::STATE_REVIEW_DUE, $reviewDue['next_state']);

        $recovered = $lifecycle->transition(
            $reviewDue['next_state'],
            MistakeRecoveryLifecycle::EVENT_REVIEW_SUCCESS,
        );
        self::assertSame(MistakeRecoveryLifecycle::STATE_RECOVERED, $recovered['next_state']);

        $mastered = $lifecycle->transition(
            $recovered['next_state'],
            MistakeRecoveryLifecycle::EVENT_MASTERY_CONFIRMED,
        );
        self::assertSame(MistakeRecoveryLifecycle::STATE_MASTERED, $mastered['next_state']);

        self::assertSame(
            $mastered,
            $lifecycle->transition(
                MistakeRecoveryLifecycle::STATE_RECOVERED,
                MistakeRecoveryLifecycle::EVENT_MASTERY_CONFIRMED,
            ),
        );
    }

    public function test_lapse_from_review_due_recovered_or_mastered_returns_to_relearning(): void
    {
        $lifecycle = new MistakeRecoveryLifecycle;

        foreach ([
            MistakeRecoveryLifecycle::STATE_REVIEW_DUE,
            MistakeRecoveryLifecycle::STATE_RECOVERED,
            MistakeRecoveryLifecycle::STATE_MASTERED,
        ] as $state) {
            $transition = $lifecycle->transition($state, MistakeRecoveryLifecycle::EVENT_LAPSE);

            self::assertSame(MistakeRecoveryLifecycle::STATE_RELEARNING, $transition['next_state']);
            self::assertTrue($transition['changed']);
        }
    }

    public function test_lapse_while_already_relearning_is_idempotent(): void
    {
        $lifecycle = new MistakeRecoveryLifecycle;

        $first = $lifecycle->transition(
            MistakeRecoveryLifecycle::STATE_RELEARNING,
            MistakeRecoveryLifecycle::EVENT_LAPSE,
        );
        $second = $lifecycle->transition(
            MistakeRecoveryLifecycle::STATE_RELEARNING,
            MistakeRecoveryLifecycle::EVENT_LAPSE,
        );

        self::assertSame($first, $second);
        self::assertSame(MistakeRecoveryLifecycle::STATE_RELEARNING, $first['next_state']);
        self::assertFalse($first['changed']);
    }

    public function test_illegal_transition_fails_closed(): void
    {
        $lifecycle = new MistakeRecoveryLifecycle;

        $this->expectException(InvalidArgumentException::class);

        $lifecycle->transition(
            MistakeRecoveryLifecycle::STATE_NEW,
            MistakeRecoveryLifecycle::EVENT_MASTERY_CONFIRMED,
        );
    }

    public function test_unknown_state_or_event_fails_closed(): void
    {
        $lifecycle = new MistakeRecoveryLifecycle;

        try {
            $lifecycle->transition('unknown', MistakeRecoveryLifecycle::EVENT_LAPSE);
            self::fail('Unknown state should fail closed.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);

        $lifecycle->transition(MistakeRecoveryLifecycle::STATE_NEW, 'unknown');
    }
}

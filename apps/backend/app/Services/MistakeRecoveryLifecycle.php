<?php

namespace App\Services;

use InvalidArgumentException;
use JsonException;

final class MistakeRecoveryLifecycle
{
    public const ALGORITHM_VERSION = 'mistake-recovery-v1';

    public const STATE_NEW = 'new';

    public const STATE_RELEARNING = 'relearning';

    public const STATE_REVIEW_DUE = 'review_due';

    public const STATE_RECOVERED = 'recovered';

    public const STATE_MASTERED = 'mastered';

    public const EVENT_BEGIN_RELEARNING = 'begin_relearning';

    public const EVENT_SCHEDULE_REVIEW = 'schedule_review';

    public const EVENT_REVIEW_SUCCESS = 'review_success';

    public const EVENT_MASTERY_CONFIRMED = 'mastery_confirmed';

    public const EVENT_LAPSE = 'lapse';

    /** @var list<string> */
    private const STATES = [
        self::STATE_NEW,
        self::STATE_RELEARNING,
        self::STATE_REVIEW_DUE,
        self::STATE_RECOVERED,
        self::STATE_MASTERED,
    ];

    /** @var list<string> */
    private const EVENTS = [
        self::EVENT_BEGIN_RELEARNING,
        self::EVENT_SCHEDULE_REVIEW,
        self::EVENT_REVIEW_SUCCESS,
        self::EVENT_MASTERY_CONFIRMED,
        self::EVENT_LAPSE,
    ];

    /**
     * @return array{
     *   algorithm_version:string,
     *   previous_state:string,
     *   event:string,
     *   next_state:string,
     *   changed:bool,
     *   transition_fingerprint:string
     * }
     *
     * @throws JsonException
     */
    public function transition(string $currentState, string $event): array
    {
        $this->validateInput($currentState, $event);

        $nextState = match ([$currentState, $event]) {
            [self::STATE_NEW, self::EVENT_BEGIN_RELEARNING] => self::STATE_RELEARNING,
            [self::STATE_RELEARNING, self::EVENT_SCHEDULE_REVIEW] => self::STATE_REVIEW_DUE,
            [self::STATE_REVIEW_DUE, self::EVENT_REVIEW_SUCCESS] => self::STATE_RECOVERED,
            [self::STATE_RECOVERED, self::EVENT_MASTERY_CONFIRMED] => self::STATE_MASTERED,

            [self::STATE_RELEARNING, self::EVENT_LAPSE] => self::STATE_RELEARNING,
            [self::STATE_REVIEW_DUE, self::EVENT_LAPSE],
            [self::STATE_RECOVERED, self::EVENT_LAPSE],
            [self::STATE_MASTERED, self::EVENT_LAPSE] => self::STATE_RELEARNING,

            default => throw new InvalidArgumentException(sprintf(
                'Illegal mistake-recovery transition: %s + %s.',
                $currentState,
                $event,
            )),
        };

        $payload = [
            'algorithm_version' => self::ALGORITHM_VERSION,
            'previous_state' => $currentState,
            'event' => $event,
            'next_state' => $nextState,
        ];

        return $payload + [
            'changed' => $nextState !== $currentState,
            'transition_fingerprint' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
    }

    private function validateInput(string $currentState, string $event): void
    {
        if (! in_array($currentState, self::STATES, true)) {
            throw new InvalidArgumentException('Unknown Mistake Notebook lifecycle state.');
        }

        if (! in_array($event, self::EVENTS, true)) {
            throw new InvalidArgumentException('Unknown Mistake Notebook lifecycle event.');
        }
    }
}

<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JsonException;

final class SpacedRepetitionScheduler
{
    public const ALGORITHM_VERSION = 'spaced-repetition-v1';

    /** @var array<string, int> */
    private const DEFAULT_BASE_INTERVAL_DAYS = [
        'critical' => 1,
        'weak' => 3,
        'developing' => 7,
        'strong' => 14,
    ];

    /**
     * @param  array<string, int>  $baseIntervals
     * @return array{
     *   algorithm_version:string,
     *   mastery_band:string,
     *   outcome:string,
     *   previous_interval_days:int|null,
     *   base_interval_days:int,
     *   next_interval_days:int,
     *   reviewed_at:string,
     *   due_at:string,
     *   policy_fingerprint:string,
     *   reason:string
     * }
     *
     * @throws JsonException
     */
    public function schedule(
        string $masteryBand,
        bool $successfulReview,
        ?int $previousIntervalDays,
        CarbonImmutable $reviewedAt,
        array $baseIntervals = self::DEFAULT_BASE_INTERVAL_DAYS,
        int $minIntervalDays = 1,
        int $maxIntervalDays = 60,
    ): array {
        $this->validatePolicy($masteryBand, $previousIntervalDays, $baseIntervals, $minIntervalDays, $maxIntervalDays);

        $baseInterval = max(
            $minIntervalDays,
            min($maxIntervalDays, $baseIntervals[$masteryBand]),
        );

        if (! $successfulReview) {
            $nextInterval = $minIntervalDays;
            $reason = 'lapse_reset';
        } elseif ($previousIntervalDays === null) {
            $nextInterval = $baseInterval;
            $reason = 'first_success';
        } else {
            $nextInterval = max(
                $baseInterval,
                min($maxIntervalDays, $previousIntervalDays + $baseInterval),
            );
            $reason = 'successful_extension';
        }

        $policyFingerprint = hash('sha256', json_encode([
            'algorithm_version' => self::ALGORITHM_VERSION,
            'base_intervals' => $baseIntervals,
            'min_interval_days' => $minIntervalDays,
            'max_interval_days' => $maxIntervalDays,
        ], JSON_THROW_ON_ERROR));

        return [
            'algorithm_version' => self::ALGORITHM_VERSION,
            'mastery_band' => $masteryBand,
            'outcome' => $successfulReview ? 'success' : 'lapse',
            'previous_interval_days' => $previousIntervalDays,
            'base_interval_days' => $baseInterval,
            'next_interval_days' => $nextInterval,
            'reviewed_at' => $reviewedAt->toIso8601String(),
            'due_at' => $reviewedAt->addDays($nextInterval)->toIso8601String(),
            'policy_fingerprint' => $policyFingerprint,
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<string, int>  $baseIntervals
     */
    private function validatePolicy(
        string $masteryBand,
        ?int $previousIntervalDays,
        array $baseIntervals,
        int $minIntervalDays,
        int $maxIntervalDays,
    ): void {
        if ($minIntervalDays < 1 || $maxIntervalDays < $minIntervalDays) {
            throw new InvalidArgumentException('Spaced-repetition interval bounds are invalid.');
        }

        $expectedBands = array_keys(self::DEFAULT_BASE_INTERVAL_DAYS);
        $actualBands = array_keys($baseIntervals);
        sort($expectedBands, SORT_STRING);
        sort($actualBands, SORT_STRING);

        if ($expectedBands !== $actualBands) {
            throw new InvalidArgumentException('Spaced-repetition policy must define exactly critical, weak, developing and strong intervals.');
        }

        foreach ($baseIntervals as $band => $days) {
            if (! is_int($days) || $days < 1 || $days > $maxIntervalDays) {
                throw new InvalidArgumentException(sprintf('Spaced-repetition interval for %s is outside the governed bounds.', $band));
            }
        }

        if (! array_key_exists($masteryBand, $baseIntervals)) {
            throw new InvalidArgumentException('Unknown mastery band for spaced-repetition scheduling.');
        }

        if ($previousIntervalDays !== null
            && ($previousIntervalDays < $minIntervalDays || $previousIntervalDays > $maxIntervalDays)) {
            throw new InvalidArgumentException('Previous spaced-repetition interval is outside the governed bounds.');
        }
    }
}

<?php

namespace Tests\Unit;

use App\Services\SpacedRepetitionScheduler;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SpacedRepetitionSchedulerTest extends TestCase
{
    public function test_identical_inputs_produce_identical_schedule(): void
    {
        $scheduler = new SpacedRepetitionScheduler();
        $reviewedAt = CarbonImmutable::parse('2026-09-19T12:00:00+00:00');

        $first = $scheduler->schedule('developing', true, 7, $reviewedAt);
        $second = $scheduler->schedule('developing', true, 7, $reviewedAt);

        self::assertSame($first, $second);
        self::assertSame(SpacedRepetitionScheduler::ALGORITHM_VERSION, $first['algorithm_version']);
        self::assertSame('success', $first['outcome']);
        self::assertSame('successful_extension', $first['reason']);
        self::assertSame(14, $first['next_interval_days']);
        self::assertSame('2026-10-03T12:00:00+00:00', $first['due_at']);
    }

    public function test_first_success_uses_the_bounded_band_base_interval(): void
    {
        $scheduler = new SpacedRepetitionScheduler();
        $reviewedAt = CarbonImmutable::parse('2026-09-19T12:00:00+00:00');

        $schedule = $scheduler->schedule('strong', true, null, $reviewedAt);

        self::assertSame('first_success', $schedule['reason']);
        self::assertSame(14, $schedule['base_interval_days']);
        self::assertSame(14, $schedule['next_interval_days']);
        self::assertSame('2026-10-03T12:00:00+00:00', $schedule['due_at']);
    }

    public function test_lapse_resets_interval_and_later_success_recovers_deterministically(): void
    {
        $scheduler = new SpacedRepetitionScheduler();
        $reviewedAt = CarbonImmutable::parse('2026-09-19T12:00:00+00:00');

        $lapse = $scheduler->schedule('strong', false, 28, $reviewedAt);

        self::assertSame('lapse', $lapse['outcome']);
        self::assertSame('lapse_reset', $lapse['reason']);
        self::assertSame(1, $lapse['next_interval_days']);
        self::assertSame('2026-09-20T12:00:00+00:00', $lapse['due_at']);

        $recovery = $scheduler->schedule(
            'weak',
            true,
            $lapse['next_interval_days'],
            $reviewedAt->addDay(),
        );

        self::assertSame('success', $recovery['outcome']);
        self::assertSame('successful_extension', $recovery['reason']);
        self::assertSame(4, $recovery['next_interval_days']);
        self::assertSame('2026-09-24T12:00:00+00:00', $recovery['due_at']);
    }

    public function test_custom_policy_and_bounds_are_reproducible_and_capped(): void
    {
        $scheduler = new SpacedRepetitionScheduler();
        $reviewedAt = CarbonImmutable::parse('2026-09-19T12:00:00+00:00');
        $policy = [
            'critical' => 2,
            'weak' => 4,
            'developing' => 8,
            'strong' => 16,
        ];

        $first = $scheduler->schedule('strong', true, 16, $reviewedAt, $policy, 2, 20);
        $repeat = $scheduler->schedule('strong', true, 16, $reviewedAt, $policy, 2, 20);

        self::assertSame($first, $repeat);
        self::assertSame(16, $first['base_interval_days']);
        self::assertSame(20, $first['next_interval_days']);
        self::assertSame('2026-10-09T12:00:00+00:00', $first['due_at']);
        self::assertSame($first['policy_fingerprint'], $repeat['policy_fingerprint']);
    }

    public function test_invalid_band_fails_closed(): void
    {
        $scheduler = new SpacedRepetitionScheduler();

        $this->expectException(InvalidArgumentException::class);

        $scheduler->schedule(
            'unknown',
            true,
            null,
            CarbonImmutable::parse('2026-09-19T12:00:00+00:00'),
        );
    }

    public function test_policy_interval_below_configured_minimum_fails_closed(): void
    {
        $scheduler = new SpacedRepetitionScheduler();

        $this->expectException(InvalidArgumentException::class);

        $scheduler->schedule(
            'critical',
            true,
            null,
            CarbonImmutable::parse('2026-09-19T12:00:00+00:00'),
            [
                'critical' => 1,
                'weak' => 4,
                'developing' => 8,
                'strong' => 16,
            ],
            2,
            20,
        );
    }

    public function test_out_of_bounds_previous_interval_fails_closed(): void
    {
        $scheduler = new SpacedRepetitionScheduler();

        $this->expectException(InvalidArgumentException::class);

        $scheduler->schedule(
            'strong',
            true,
            61,
            CarbonImmutable::parse('2026-09-19T12:00:00+00:00'),
        );
    }
}

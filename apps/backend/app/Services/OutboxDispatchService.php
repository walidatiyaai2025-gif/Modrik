<?php

namespace App\Services;

use App\Events\OutboxMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

final class OutboxDispatchService
{
    /**
     * @return array{scanned: int, published: int, already_published: int, failed: int, deferred: int, exhausted: int}
     */
    public function dispatchBatch(int $limit): array
    {
        $maximumAttempts = max(1, (int) config('modrik.outbox.maximum_attempts', 5));
        $now = now();
        $latestAttemptSql = '(select coalesce(max(attempt_state.attempt_number), 0) from outbox_delivery_attempts as attempt_state where attempt_state.outbox_event_id = outbox_events.id)';
        $latestRedriveSql = '(select coalesce(max(redrive_state.exhausted_attempt_number), 0) from outbox_redrive_requests as redrive_state where redrive_state.outbox_event_id = outbox_events.id)';

        $eventIds = DB::table('outbox_events')
            ->whereNull('published_at')
            ->whereRaw("({$latestAttemptSql} - {$latestRedriveSql}) < ?", [$maximumAttempts])
            ->whereNotExists(function ($query) use ($now): void {
                $query->selectRaw('1')
                    ->from('outbox_delivery_attempts as deferred_attempts')
                    ->whereColumn('deferred_attempts.outbox_event_id', 'outbox_events.id')
                    ->where('deferred_attempts.status', 'failed')
                    ->where('deferred_attempts.next_attempt_at', '>', $now)
                    ->whereRaw('deferred_attempts.attempt_number > (select coalesce(max(deferred_redrive.exhausted_attempt_number), 0) from outbox_redrive_requests as deferred_redrive where deferred_redrive.outbox_event_id = deferred_attempts.outbox_event_id)');
            })
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        $summary = [
            'scanned' => count($eventIds),
            'published' => 0,
            'already_published' => 0,
            'failed' => 0,
            'deferred' => 0,
            'exhausted' => 0,
        ];
        foreach ($eventIds as $eventId) {
            $result = $this->dispatchOne($eventId, $maximumAttempts);
            $summary[$result]++;
        }
        $summary['deferred'] = $this->blockedCount($maximumAttempts, false);
        $summary['exhausted'] = $this->blockedCount($maximumAttempts, true);

        return $summary;
    }

    private function blockedCount(int $maximumAttempts, bool $exhausted): int
    {
        $latestAttemptSql = '(select coalesce(max(attempt_state.attempt_number), 0) from outbox_delivery_attempts as attempt_state where attempt_state.outbox_event_id = outbox_events.id)';
        $latestRedriveSql = '(select coalesce(max(redrive_state.exhausted_attempt_number), 0) from outbox_redrive_requests as redrive_state where redrive_state.outbox_event_id = outbox_events.id)';
        $query = DB::table('outbox_events')->whereNull('published_at');

        if ($exhausted) {
            return $query
                ->whereRaw("({$latestAttemptSql} - {$latestRedriveSql}) >= ?", [$maximumAttempts])
                ->count();
        }

        return $query
            ->whereRaw("({$latestAttemptSql} - {$latestRedriveSql}) < ?", [$maximumAttempts])
            ->whereExists(function ($deferred): void {
                $deferred->selectRaw('1')
                    ->from('outbox_delivery_attempts as deferred_attempts')
                    ->whereColumn('deferred_attempts.outbox_event_id', 'outbox_events.id')
                    ->where('deferred_attempts.status', 'failed')
                    ->where('deferred_attempts.next_attempt_at', '>', now())
                    ->whereRaw('deferred_attempts.attempt_number > (select coalesce(max(deferred_redrive.exhausted_attempt_number), 0) from outbox_redrive_requests as deferred_redrive where deferred_redrive.outbox_event_id = deferred_attempts.outbox_event_id)');
            })
            ->count();
    }

    /** @return 'published'|'already_published'|'failed'|'deferred'|'exhausted' */
    private function dispatchOne(string $eventId, int $maximumAttempts): string
    {
        return DB::transaction(function () use ($eventId, $maximumAttempts): string {
            $event = DB::table('outbox_events')->where('id', $eventId)->lockForUpdate()->first();
            if ($event === null || $event->published_at !== null) {
                return 'already_published';
            }

            $latestAttemptNumber = (int) DB::table('outbox_delivery_attempts')
                ->where('outbox_event_id', $eventId)
                ->max('attempt_number');
            $redriveBase = (int) DB::table('outbox_redrive_requests')
                ->where('outbox_event_id', $eventId)
                ->max('exhausted_attempt_number');
            if ($redriveBase > $latestAttemptNumber) {
                return 'exhausted';
            }

            $currentCycleAttempts = $latestAttemptNumber - $redriveBase;
            if ($currentCycleAttempts >= $maximumAttempts) {
                return 'exhausted';
            }
            if (DB::table('outbox_delivery_attempts')
                ->where('outbox_event_id', $eventId)
                ->where('attempt_number', '>', $redriveBase)
                ->where('status', 'failed')
                ->where('next_attempt_at', '>', now())
                ->exists()) {
                return 'deferred';
            }

            $attemptNumber = $latestAttemptNumber + 1;
            $cycleAttemptNumber = $currentCycleAttempts + 1;
            $attemptId = (string) Str::ulid();
            $startedAt = now();
            DB::table('outbox_delivery_attempts')->insert([
                'id' => $attemptId,
                'outbox_event_id' => $eventId,
                'attempt_number' => $attemptNumber,
                'status' => 'started',
                'started_at' => $startedAt,
                'created_at' => $startedAt,
                'updated_at' => $startedAt,
            ]);

            try {
                $payload = json_decode((string) $event->payload, true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($payload) || array_is_list($payload)) {
                    throw new JsonException('Outbox payload must be an object.');
                }
                Event::dispatch(new OutboxMessage(
                    eventId: (string) $event->id,
                    eventType: (string) $event->event_type,
                    aggregateType: (string) $event->aggregate_type,
                    aggregateId: (string) $event->aggregate_id,
                    payload: $payload,
                    occurredAt: (string) $event->occurred_at,
                ));

                $finishedAt = now();
                DB::table('outbox_events')->where('id', $eventId)->update([
                    'published_at' => $finishedAt,
                    'updated_at' => $finishedAt,
                ]);
                DB::table('outbox_delivery_attempts')->where('id', $attemptId)->update([
                    'status' => 'published',
                    'finished_at' => $finishedAt,
                    'updated_at' => $finishedAt,
                ]);
                $this->markRedriveRecovered($eventId, $attemptNumber, $finishedAt);

                return 'published';
            } catch (Throwable $exception) {
                $finishedAt = now();
                $backoffSeconds = min(
                    (int) config('modrik.outbox.maximum_backoff_seconds', 3600),
                    (int) config('modrik.outbox.initial_backoff_seconds', 60) * (2 ** ($cycleAttemptNumber - 1)),
                );
                DB::table('outbox_delivery_attempts')->where('id', $attemptId)->update([
                    'status' => 'failed',
                    'error_code' => 'DELIVERY_EXCEPTION',
                    'error_fingerprint' => hash('sha256', $exception::class."\0".$exception->getMessage()),
                    'finished_at' => $finishedAt,
                    'next_attempt_at' => $finishedAt->copy()->addSeconds($backoffSeconds),
                    'updated_at' => $finishedAt,
                ]);

                return 'failed';
            }
        }, 3);
    }

    private function markRedriveRecovered(string $eventId, int $attemptNumber, Carbon $finishedAt): void
    {
        $request = DB::table('outbox_redrive_requests')
            ->where('outbox_event_id', $eventId)
            ->where('status', 'requested')
            ->orderByDesc('exhausted_attempt_number')
            ->first();
        if ($request === null || (int) $request->exhausted_attempt_number >= $attemptNumber) {
            return;
        }

        DB::table('outbox_redrive_requests')->where('id', $request->id)->update([
            'status' => 'recovered',
            'resolved_at' => $finishedAt,
            'successful_attempt_number' => $attemptNumber,
            'updated_at' => $finishedAt,
        ]);
    }
}

<?php

namespace App\Services;

use App\Events\OutboxMessage;
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
        $maximumAttempts = (int) config('modrik.outbox.maximum_attempts', 5);
        $now = now();
        $eventIds = DB::table('outbox_events')
            ->whereNull('published_at')
            ->whereNotExists(function ($query) use ($maximumAttempts, $now): void {
                $query->selectRaw('1')
                    ->from('outbox_delivery_attempts as blocked_attempts')
                    ->whereColumn('blocked_attempts.outbox_event_id', 'outbox_events.id')
                    ->where(function ($blocked) use ($maximumAttempts, $now): void {
                        $blocked->where('blocked_attempts.attempt_number', '>=', $maximumAttempts)
                            ->orWhere(function ($deferred) use ($now): void {
                                $deferred->where('blocked_attempts.status', 'failed')
                                    ->where('blocked_attempts.next_attempt_at', '>', $now);
                            });
                    });
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
        return DB::table('outbox_events')
            ->whereNull('published_at')
            ->whereExists(function ($query) use ($maximumAttempts, $exhausted): void {
                $query->selectRaw('1')
                    ->from('outbox_delivery_attempts as delivery_state')
                    ->whereColumn('delivery_state.outbox_event_id', 'outbox_events.id')
                    ->where('delivery_state.status', 'failed');
                if ($exhausted) {
                    $query->where('delivery_state.attempt_number', '>=', $maximumAttempts);
                } else {
                    $query->where('delivery_state.attempt_number', '<', $maximumAttempts)
                        ->where('delivery_state.next_attempt_at', '>', now());
                }
            })
            ->count();
    }

    /** @return 'published'|'already_published'|'failed' */
    private function dispatchOne(string $eventId, int $maximumAttempts): string
    {
        return DB::transaction(function () use ($eventId, $maximumAttempts): string {
            $event = DB::table('outbox_events')->where('id', $eventId)->lockForUpdate()->first();
            if ($event === null || $event->published_at !== null) {
                return 'already_published';
            }

            $attemptNumber = (int) DB::table('outbox_delivery_attempts')
                ->where('outbox_event_id', $eventId)
                ->max('attempt_number') + 1;
            if ($attemptNumber > $maximumAttempts) {
                return 'already_published';
            }

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

                return 'published';
            } catch (Throwable $exception) {
                $finishedAt = now();
                $backoffSeconds = min(
                    (int) config('modrik.outbox.maximum_backoff_seconds', 3600),
                    (int) config('modrik.outbox.initial_backoff_seconds', 60) * (2 ** ($attemptNumber - 1)),
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
}

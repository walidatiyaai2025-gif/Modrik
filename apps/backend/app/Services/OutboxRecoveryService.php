<?php

namespace App\Services;

use App\Events\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

final class OutboxRecoveryService
{
    /**
     * @return array{
     *     event_id: string,
     *     request_id: string,
     *     status: 'published'|'failed'|'already_published'|'not_found'|'not_exhausted',
     *     replayed: bool,
     *     attempt_number: int|null
     * }
     */
    public function redrive(string $eventId, string $requestId): array
    {
        $maximumAttempts = max(1, (int) config('modrik.outbox.maximum_attempts', 5));

        return DB::transaction(function () use ($eventId, $requestId, $maximumAttempts): array {
            $event = DB::table('outbox_events')->where('id', $eventId)->lockForUpdate()->first();
            if ($event === null) {
                return $this->result($eventId, $requestId, 'not_found');
            }

            $existingRecovery = DB::table('outbox_recovery_actions')
                ->where('outbox_event_id', $eventId)
                ->where('request_id', $requestId)
                ->first();
            if ($existingRecovery !== null) {
                $status = (string) $existingRecovery->status;
                if ($status !== 'published' && $status !== 'failed') {
                    $status = 'failed';
                }

                return $this->result(
                    $eventId,
                    $requestId,
                    $status,
                    true,
                    (int) $existingRecovery->attempt_number,
                );
            }

            if ($event->published_at !== null) {
                return $this->result($eventId, $requestId, 'already_published');
            }

            $lastAttempt = DB::table('outbox_delivery_attempts')
                ->where('outbox_event_id', $eventId)
                ->orderByDesc('attempt_number')
                ->first();
            if (
                $lastAttempt === null
                || (int) $lastAttempt->attempt_number < $maximumAttempts
                || (string) $lastAttempt->status !== 'failed'
            ) {
                return $this->result($eventId, $requestId, 'not_exhausted');
            }

            $attemptNumber = (int) $lastAttempt->attempt_number + 1;
            $attemptId = (string) Str::ulid();
            $recoveryId = (string) Str::ulid();
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
            DB::table('outbox_recovery_actions')->insert([
                'id' => $recoveryId,
                'outbox_event_id' => $eventId,
                'request_id' => $requestId,
                'delivery_attempt_id' => $attemptId,
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
                DB::table('outbox_recovery_actions')->where('id', $recoveryId)->update([
                    'status' => 'published',
                    'finished_at' => $finishedAt,
                    'updated_at' => $finishedAt,
                ]);

                return $this->result($eventId, $requestId, 'published', false, $attemptNumber);
            } catch (Throwable $exception) {
                $finishedAt = now();
                $fingerprint = hash('sha256', $exception::class."\0".$exception->getMessage());
                DB::table('outbox_delivery_attempts')->where('id', $attemptId)->update([
                    'status' => 'failed',
                    'error_code' => 'DELIVERY_EXCEPTION',
                    'error_fingerprint' => $fingerprint,
                    'finished_at' => $finishedAt,
                    'next_attempt_at' => null,
                    'updated_at' => $finishedAt,
                ]);
                DB::table('outbox_recovery_actions')->where('id', $recoveryId)->update([
                    'status' => 'failed',
                    'error_code' => 'DELIVERY_EXCEPTION',
                    'error_fingerprint' => $fingerprint,
                    'finished_at' => $finishedAt,
                    'updated_at' => $finishedAt,
                ]);

                return $this->result($eventId, $requestId, 'failed', false, $attemptNumber);
            }
        }, 3);
    }

    /**
     * @param  'published'|'failed'|'already_published'|'not_found'|'not_exhausted'  $status
     * @return array{
     *     event_id: string,
     *     request_id: string,
     *     status: 'published'|'failed'|'already_published'|'not_found'|'not_exhausted',
     *     replayed: bool,
     *     attempt_number: int|null
     * }
     */
    private function result(
        string $eventId,
        string $requestId,
        string $status,
        bool $replayed = false,
        ?int $attemptNumber = null,
    ): array {
        return [
            'event_id' => $eventId,
            'request_id' => $requestId,
            'status' => $status,
            'replayed' => $replayed,
            'attempt_number' => $attemptNumber,
        ];
    }
}

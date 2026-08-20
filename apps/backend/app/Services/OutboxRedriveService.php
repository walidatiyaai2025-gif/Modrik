<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OutboxRedriveService
{
    /**
     * @return array{status: 'redrive_requested'|'already_requested'|'not_found'|'already_published'|'not_exhausted', event_id: string, exhausted_attempt_number?: int}
     */
    public function requestRedrive(string $eventId): array
    {
        $maximumAttempts = max(1, (int) config('modrik.outbox.maximum_attempts', 5));

        return DB::transaction(function () use ($eventId, $maximumAttempts): array {
            $event = DB::table('outbox_events')
                ->where('id', $eventId)
                ->lockForUpdate()
                ->first();

            if ($event === null) {
                return ['status' => 'not_found', 'event_id' => $eventId];
            }
            if ($event->published_at !== null) {
                return ['status' => 'already_published', 'event_id' => $eventId];
            }

            $latestAttempt = DB::table('outbox_delivery_attempts')
                ->where('outbox_event_id', $eventId)
                ->orderByDesc('attempt_number')
                ->first();
            if ($latestAttempt === null || $latestAttempt->status !== 'failed') {
                return ['status' => 'not_exhausted', 'event_id' => $eventId];
            }

            $latestAttemptNumber = (int) $latestAttempt->attempt_number;
            $existing = DB::table('outbox_redrive_requests')
                ->where('outbox_event_id', $eventId)
                ->where('exhausted_attempt_number', $latestAttemptNumber)
                ->first();
            if ($existing !== null) {
                return [
                    'status' => 'already_requested',
                    'event_id' => $eventId,
                    'exhausted_attempt_number' => $latestAttemptNumber,
                ];
            }

            $previousRedriveBase = (int) DB::table('outbox_redrive_requests')
                ->where('outbox_event_id', $eventId)
                ->max('exhausted_attempt_number');
            if (($latestAttemptNumber - $previousRedriveBase) < $maximumAttempts) {
                return ['status' => 'not_exhausted', 'event_id' => $eventId];
            }

            $requestedAt = now();
            DB::table('outbox_redrive_requests')
                ->where('outbox_event_id', $eventId)
                ->where('status', 'requested')
                ->update([
                    'status' => 'reexhausted',
                    'resolved_at' => $requestedAt,
                    'updated_at' => $requestedAt,
                ]);

            DB::table('outbox_redrive_requests')->insert([
                'id' => (string) Str::ulid(),
                'outbox_event_id' => $eventId,
                'exhausted_attempt_number' => $latestAttemptNumber,
                'status' => 'requested',
                'requested_at' => $requestedAt,
                'created_at' => $requestedAt,
                'updated_at' => $requestedAt,
            ]);

            return [
                'status' => 'redrive_requested',
                'event_id' => $eventId,
                'exhausted_attempt_number' => $latestAttemptNumber,
            ];
        }, 3);
    }
}

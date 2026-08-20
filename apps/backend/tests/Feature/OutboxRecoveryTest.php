<?php

namespace Tests\Feature;

use App\Events\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class OutboxRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_redrive_recovers_one_exhausted_unsent_event_and_replays_idempotently(): void
    {
        config(['modrik.outbox.maximum_attempts' => 2]);
        $eventId = $this->insertOutbox('fixture.recovery', ['secret_marker' => 'payload-must-never-be-printed']);
        $originalPayload = (string) DB::table('outbox_events')->where('id', $eventId)->value('payload');
        $listenerState = new class
        {
            public bool $shouldFail = true;
        };
        $deliveries = [];
        Event::listen(OutboxMessage::class, function (OutboxMessage $message) use ($listenerState, &$deliveries): void {
            $deliveries[] = [
                'event_id' => $message->eventId,
                'event_type' => $message->eventType,
                'payload' => $message->payload,
            ];
            if ($listenerState->shouldFail) {
                throw new RuntimeException('sensitive transport failure must not reach operator output');
            }
        });

        $this->dispatchCommand(1)->assertFailed();
        DB::table('outbox_delivery_attempts')->where('outbox_event_id', $eventId)->update(['next_attempt_at' => now()->subSecond()]);
        $this->dispatchCommand(1)->assertFailed();
        $this->dispatchCommand(1)
            ->expectsOutput('{"scanned":0,"published":0,"already_published":0,"failed":0,"deferred":0,"exhausted":1}')
            ->assertFailed();
        $this->assertDatabaseCount('outbox_delivery_attempts', 2);

        $requestId = (string) Str::ulid();
        $this->redriveCommand($eventId, $requestId, 'WRONG-CONFIRMATION')
            ->expectsOutput('The --confirm option must equal REDRIVE-EXHAUSTED.')
            ->assertExitCode(2);
        $this->assertDatabaseCount('outbox_recovery_actions', 0);
        $this->assertDatabaseCount('outbox_delivery_attempts', 2);

        $listenerState->shouldFail = false;
        $this->redriveCommand($eventId, $requestId)
            ->expectsOutput($this->recoveryOutput($eventId, $requestId, 'published', false, 3))
            ->assertSuccessful();

        $this->assertNotNull(DB::table('outbox_events')->where('id', $eventId)->value('published_at'));
        $this->assertSame($originalPayload, DB::table('outbox_events')->where('id', $eventId)->value('payload'));
        $this->assertSame([1, 2, 3], DB::table('outbox_delivery_attempts')->where('outbox_event_id', $eventId)->orderBy('attempt_number')->pluck('attempt_number')->map(fn (mixed $value): int => (int) $value)->all());
        $this->assertDatabaseHas('outbox_recovery_actions', [
            'outbox_event_id' => $eventId,
            'request_id' => $requestId,
            'attempt_number' => 3,
            'status' => 'published',
            'error_code' => null,
        ]);
        $this->assertCount(3, $deliveries);
        foreach ($deliveries as $delivery) {
            $this->assertSame($eventId, $delivery['event_id']);
            $this->assertSame('fixture.recovery', $delivery['event_type']);
            $this->assertSame(['secret_marker' => 'payload-must-never-be-printed'], $delivery['payload']);
        }

        $this->redriveCommand($eventId, $requestId)
            ->expectsOutput($this->recoveryOutput($eventId, $requestId, 'published', true, 3))
            ->assertSuccessful();
        $this->assertCount(3, $deliveries, 'Exact operator-action replay must not dispatch a completed event again.');
        $this->assertDatabaseCount('outbox_recovery_actions', 1);
        $this->assertDatabaseCount('outbox_delivery_attempts', 3);

        $newRequestId = (string) Str::ulid();
        $this->redriveCommand($eventId, $newRequestId)
            ->expectsOutput($this->recoveryOutput($eventId, $newRequestId, 'already_published', false, null))
            ->assertFailed();
        $this->assertCount(3, $deliveries);
        $this->assertDatabaseCount('outbox_recovery_actions', 1);
        $this->assertDatabaseCount('outbox_delivery_attempts', 3);
    }

    public function test_failed_recovery_is_sanitized_bounded_and_requires_a_new_explicit_request(): void
    {
        config(['modrik.outbox.maximum_attempts' => 1]);
        $eventId = $this->insertOutbox('fixture.recovery-failure', ['opaque_reference' => 'private-payload-marker']);
        $listenerState = new class
        {
            public bool $shouldFail = true;
        };
        $deliveries = [];
        Event::listen(OutboxMessage::class, function (OutboxMessage $message) use ($listenerState, &$deliveries): void {
            $deliveries[] = $message->eventId;
            if ($listenerState->shouldFail) {
                throw new RuntimeException('private-payload-marker secret-like recovery exception');
            }
        });

        $this->dispatchCommand(1)->assertFailed();
        $firstRequestId = (string) Str::ulid();
        $this->redriveCommand($eventId, $firstRequestId)
            ->expectsOutput($this->recoveryOutput($eventId, $firstRequestId, 'failed', false, 2))
            ->assertFailed();

        $recovery = DB::table('outbox_recovery_actions')->where('outbox_event_id', $eventId)->first();
        $this->assertNotNull($recovery);
        $this->assertSame('failed', $recovery->status);
        $this->assertSame('DELIVERY_EXCEPTION', $recovery->error_code);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $recovery->error_fingerprint);
        $this->assertStringNotContainsString('private-payload-marker', json_encode((array) $recovery, JSON_THROW_ON_ERROR));

        $deliveryAttempt = DB::table('outbox_delivery_attempts')->where('outbox_event_id', $eventId)->where('attempt_number', 2)->first();
        $this->assertNotNull($deliveryAttempt);
        $this->assertSame('failed', $deliveryAttempt->status);
        $this->assertNull($deliveryAttempt->next_attempt_at, 'Explicit recovery failure must not schedule another automatic retry.');
        $this->assertStringNotContainsString('secret-like', json_encode((array) $deliveryAttempt, JSON_THROW_ON_ERROR));

        $this->dispatchCommand(1)
            ->expectsOutput('{"scanned":0,"published":0,"already_published":0,"failed":0,"deferred":0,"exhausted":1}')
            ->assertFailed();
        $this->assertSame([$eventId, $eventId], $deliveries);

        $this->redriveCommand($eventId, $firstRequestId)
            ->expectsOutput($this->recoveryOutput($eventId, $firstRequestId, 'failed', true, 2))
            ->assertFailed();
        $this->assertSame([$eventId, $eventId], $deliveries, 'Retrying the same recovery request must not perform another delivery.');
        $this->assertDatabaseCount('outbox_recovery_actions', 1);
        $this->assertDatabaseCount('outbox_delivery_attempts', 2);

        $listenerState->shouldFail = false;
        $secondRequestId = (string) Str::ulid();
        $this->redriveCommand($eventId, $secondRequestId)
            ->expectsOutput($this->recoveryOutput($eventId, $secondRequestId, 'published', false, 3))
            ->assertSuccessful();
        $this->assertSame([$eventId, $eventId, $eventId], $deliveries);
        $this->assertDatabaseCount('outbox_recovery_actions', 2);
        $this->assertDatabaseCount('outbox_delivery_attempts', 3);
        $this->assertSame(1, DB::table('outbox_recovery_actions')->where('status', 'published')->count());
        $this->assertSame(1, DB::table('outbox_recovery_actions')->where('status', 'failed')->count());
    }

    public function test_only_exhausted_unsent_events_with_valid_operator_inputs_are_eligible(): void
    {
        config(['modrik.outbox.maximum_attempts' => 2]);
        $eventId = $this->insertOutbox('fixture.not-exhausted');
        $requestId = (string) Str::ulid();

        $this->redriveCommand('not-a-ulid', $requestId)
            ->expectsOutput('The event_id argument must be a valid ULID.')
            ->assertExitCode(2);
        $this->redriveCommand($eventId, 'not-a-ulid')
            ->expectsOutput('The --request-id option must be a valid ULID.')
            ->assertExitCode(2);

        $this->redriveCommand($eventId, $requestId)
            ->expectsOutput($this->recoveryOutput($eventId, $requestId, 'not_exhausted', false, null))
            ->assertFailed();
        $this->assertDatabaseCount('outbox_recovery_actions', 0);

        Event::listen(OutboxMessage::class, static function (): never {
            throw new RuntimeException('one failure is below the configured cap');
        });
        $this->dispatchCommand(1)->assertFailed();
        $this->redriveCommand($eventId, $requestId)
            ->expectsOutput($this->recoveryOutput($eventId, $requestId, 'not_exhausted', false, null))
            ->assertFailed();
        $this->assertDatabaseCount('outbox_recovery_actions', 0);

        $unknownEventId = (string) Str::ulid();
        $unknownRequestId = (string) Str::ulid();
        $this->redriveCommand($unknownEventId, $unknownRequestId)
            ->expectsOutput($this->recoveryOutput($unknownEventId, $unknownRequestId, 'not_found', false, null))
            ->assertFailed();
        $this->assertDatabaseCount('outbox_recovery_actions', 0);
    }

    /** @param array<string, mixed> $payload */
    private function insertOutbox(string $eventType, array $payload = ['opaque_reference' => 'fixture']): string
    {
        $id = (string) Str::ulid();
        DB::table('outbox_events')->insert([
            'id' => $id,
            'aggregate_type' => 'fixture',
            'aggregate_id' => (string) Str::ulid(),
            'event_type' => $eventType,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function dispatchCommand(int|string $limit): PendingCommand
    {
        $command = $this->artisan('modrik:outbox-dispatch', ['--limit' => $limit]);
        if (! $command instanceof PendingCommand) {
            throw new LogicException('Console output capture is unavailable.');
        }

        return $command;
    }

    private function redriveCommand(string $eventId, string $requestId, string $confirm = 'REDRIVE-EXHAUSTED'): PendingCommand
    {
        $command = $this->artisan('modrik:outbox-redrive', [
            'event_id' => $eventId,
            '--request-id' => $requestId,
            '--confirm' => $confirm,
        ]);
        if (! $command instanceof PendingCommand) {
            throw new LogicException('Console output capture is unavailable.');
        }

        return $command;
    }

    private function recoveryOutput(
        string $eventId,
        string $requestId,
        string $status,
        bool $replayed,
        ?int $attemptNumber,
    ): string {
        return json_encode([
            'event_id' => $eventId,
            'request_id' => $requestId,
            'status' => $status,
            'replayed' => $replayed,
            'attempt_number' => $attemptNumber,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}

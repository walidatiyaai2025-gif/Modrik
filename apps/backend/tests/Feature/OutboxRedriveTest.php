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

class OutboxRedriveTest extends TestCase
{
    use RefreshDatabase;

    public function test_exhausted_event_requires_explicit_redrive_and_recovers_with_same_identity(): void
    {
        config(['modrik.outbox.maximum_attempts' => 2]);
        $eventId = $this->insertOutbox('fixture.redrive', ['secret_marker' => 'must-never-appear-in-recovery-audit']);
        $originalPayload = DB::table('outbox_events')->where('id', $eventId)->value('payload');
        $listenerState = new class
        {
            public bool $shouldFail = true;
        };
        $deliveries = [];
        Event::listen(OutboxMessage::class, function (OutboxMessage $message) use ($listenerState, &$deliveries): void {
            $deliveries[] = [$message->eventId, $message->eventType, $message->payload];
            if ($listenerState->shouldFail) {
                throw new RuntimeException('sensitive transport detail must remain fingerprint-only');
            }
        });

        $this->dispatchCommand(1)->assertFailed();
        $this->releaseBackoff($eventId);
        $this->dispatchCommand(1)->assertFailed();
        $this->dispatchCommand(1)
            ->expectsOutput('{"scanned":0,"published":0,"already_published":0,"failed":0,"deferred":0,"exhausted":1}')
            ->assertFailed();

        $this->redriveCommand($eventId)
            ->expectsOutput('Explicit --confirm is required after engineering review.')
            ->assertExitCode(2);
        $this->assertDatabaseCount('outbox_redrive_requests', 0);

        $requested = json_encode([
            'status' => 'redrive_requested',
            'event_id' => $eventId,
            'exhausted_attempt_number' => 2,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $this->redriveCommand($eventId, true)
            ->expectsOutput($requested)
            ->assertSuccessful();
        $this->redriveCommand($eventId, true)
            ->expectsOutput(json_encode([
                'status' => 'already_requested',
                'event_id' => $eventId,
                'exhausted_attempt_number' => 2,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))
            ->assertSuccessful();
        $this->assertDatabaseCount('outbox_redrive_requests', 1);

        $listenerState->shouldFail = false;
        $this->dispatchCommand(1)
            ->expectsOutput('{"scanned":1,"published":1,"already_published":0,"failed":0,"deferred":0,"exhausted":0}')
            ->assertSuccessful();

        $this->assertSame([$eventId, $eventId, $eventId], array_column($deliveries, 0));
        $this->assertSame(['fixture.redrive', 'fixture.redrive', 'fixture.redrive'], array_column($deliveries, 1));
        $this->assertSame($originalPayload, DB::table('outbox_events')->where('id', $eventId)->value('payload'));
        $this->assertSame(
            [1, 2, 3],
            DB::table('outbox_delivery_attempts')
                ->where('outbox_event_id', $eventId)
                ->orderBy('attempt_number')
                ->pluck('attempt_number')
                ->map(fn (mixed $value): int => (int) $value)
                ->all(),
        );

        $audit = DB::table('outbox_redrive_requests')->where('outbox_event_id', $eventId)->first();
        $this->assertNotNull($audit);
        $this->assertSame('recovered', $audit->status);
        $this->assertSame(2, (int) $audit->exhausted_attempt_number);
        $this->assertSame(3, (int) $audit->successful_attempt_number);
        $this->assertNotNull($audit->resolved_at);
        $this->assertStringNotContainsString('must-never-appear', json_encode((array) $audit, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('sensitive transport detail', json_encode((array) $audit, JSON_THROW_ON_ERROR));

        $this->dispatchCommand(1)
            ->expectsOutput('{"scanned":0,"published":0,"already_published":0,"failed":0,"deferred":0,"exhausted":0}')
            ->assertSuccessful();
        $this->assertCount(3, $deliveries, 'A recovered published event must not be delivered again.');
    }

    public function test_sent_and_non_exhausted_events_fail_closed_for_redrive(): void
    {
        config(['modrik.outbox.maximum_attempts' => 2]);
        $sentEventId = $this->insertOutbox('fixture.sent');
        $this->dispatchCommand(1)->assertSuccessful();
        $this->redriveCommand($sentEventId, true)
            ->expectsOutput(json_encode([
                'status' => 'already_published',
                'event_id' => $sentEventId,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))
            ->assertFailed();

        $pendingEventId = $this->insertOutbox('fixture.not-exhausted');
        Event::listen(OutboxMessage::class, static function (OutboxMessage $message) use ($pendingEventId): void {
            if ($message->eventId === $pendingEventId) {
                throw new RuntimeException('first failure only');
            }
        });
        $this->dispatchCommand(1)->assertFailed();
        $this->redriveCommand($pendingEventId, true)
            ->expectsOutput(json_encode([
                'status' => 'not_exhausted',
                'event_id' => $pendingEventId,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))
            ->assertFailed();

        $this->redriveCommand('not-a-ulid', true)
            ->expectsOutput('The event argument must be a valid ULID.')
            ->assertExitCode(2);
        $this->assertDatabaseCount('outbox_redrive_requests', 0);
    }

    public function test_reexhausted_redrive_cycle_requires_another_explicit_operator_request(): void
    {
        config(['modrik.outbox.maximum_attempts' => 2]);
        $eventId = $this->insertOutbox('fixture.reexhausted');
        Event::listen(OutboxMessage::class, static function (OutboxMessage $message) use ($eventId): void {
            if ($message->eventId === $eventId) {
                throw new RuntimeException('transport remains unavailable');
            }
        });

        $this->exhaustCurrentCycle($eventId);
        $this->redriveCommand($eventId, true)->assertSuccessful();
        $this->exhaustCurrentCycle($eventId);

        $reexhausted = DB::table('outbox_redrive_requests')->where('outbox_event_id', $eventId)->first();
        $this->assertNotNull($reexhausted);
        $this->assertSame('reexhausted', $reexhausted->status);
        $this->assertNotNull($reexhausted->resolved_at);

        $this->dispatchCommand(1)
            ->expectsOutput('{"scanned":0,"published":0,"already_published":0,"failed":0,"deferred":0,"exhausted":1}')
            ->assertFailed();

        $this->redriveCommand($eventId, true)
            ->expectsOutput(json_encode([
                'status' => 'redrive_requested',
                'event_id' => $eventId,
                'exhausted_attempt_number' => 4,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))
            ->assertSuccessful();

        $requests = DB::table('outbox_redrive_requests')
            ->where('outbox_event_id', $eventId)
            ->orderBy('exhausted_attempt_number')
            ->get();
        $this->assertCount(2, $requests);
        $firstRequest = $requests->get(0);
        $secondRequest = $requests->get(1);
        $this->assertNotNull($firstRequest);
        $this->assertNotNull($secondRequest);
        $this->assertSame('reexhausted', $firstRequest->status);
        $this->assertNotNull($firstRequest->resolved_at);
        $this->assertSame('requested', $secondRequest->status);
        $this->assertSame([2, 4], $requests->pluck('exhausted_attempt_number')->map(fn (mixed $value): int => (int) $value)->all());
        $this->assertSame([1, 2, 3, 4], DB::table('outbox_delivery_attempts')->where('outbox_event_id', $eventId)->orderBy('attempt_number')->pluck('attempt_number')->map(fn (mixed $value): int => (int) $value)->all());
    }

    /** @param array<string, scalar|null> $payload */
    private function insertOutbox(string $eventType, array $payload = []): string
    {
        $id = (string) Str::ulid();
        DB::table('outbox_events')->insert([
            'id' => $id,
            'aggregate_type' => 'fixture',
            'aggregate_id' => (string) Str::ulid(),
            'event_type' => $eventType,
            'payload' => json_encode($payload + ['opaque_reference' => (string) Str::ulid()], JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function exhaustCurrentCycle(string $eventId): void
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->dispatchCommand(1)->assertFailed();
            $this->releaseBackoff($eventId);
        }
    }

    private function releaseBackoff(string $eventId): void
    {
        DB::table('outbox_delivery_attempts')
            ->where('outbox_event_id', $eventId)
            ->where('status', 'failed')
            ->update(['next_attempt_at' => now()->subSecond()]);
    }

    private function dispatchCommand(int|string $limit): PendingCommand
    {
        $command = $this->artisan('modrik:outbox-dispatch', ['--limit' => $limit]);
        if (! $command instanceof PendingCommand) {
            throw new LogicException('Console output capture is unavailable.');
        }

        return $command;
    }

    private function redriveCommand(string $eventId, bool $confirm = false): PendingCommand
    {
        $arguments = ['event' => $eventId];
        if ($confirm) {
            $arguments['--confirm'] = true;
        }
        $command = $this->artisan('modrik:outbox-redrive', $arguments);
        if (! $command instanceof PendingCommand) {
            throw new LogicException('Console output capture is unavailable.');
        }

        return $command;
    }
}

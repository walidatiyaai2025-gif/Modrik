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

class OutboxDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_bounded_batches_and_never_republishes_completed_rows(): void
    {
        $eventIds = [
            $this->insertOutbox('fixture.first'),
            $this->insertOutbox('fixture.second'),
            $this->insertOutbox('fixture.third'),
        ];
        $delivered = [];
        Event::listen(OutboxMessage::class, function (OutboxMessage $message) use (&$delivered): void {
            $delivered[] = $message->eventId;
        });

        $this->dispatchCommand(2)
            ->expectsOutput('{"scanned":2,"published":2,"already_published":0,"failed":0,"deferred":0,"exhausted":0}')
            ->assertSuccessful();
        $this->assertCount(2, $delivered);
        $this->assertSame(2, DB::table('outbox_events')->whereNotNull('published_at')->count());
        $this->assertDatabaseCount('outbox_delivery_attempts', 2);

        $this->dispatchCommand(2)
            ->expectsOutput('{"scanned":1,"published":1,"already_published":0,"failed":0,"deferred":0,"exhausted":0}')
            ->assertSuccessful();
        $this->assertSame($eventIds, $delivered);

        $this->dispatchCommand(2)
            ->expectsOutput('{"scanned":0,"published":0,"already_published":0,"failed":0,"deferred":0,"exhausted":0}')
            ->assertSuccessful();
        $this->assertSame($eventIds, $delivered);
        $this->assertDatabaseCount('outbox_delivery_attempts', 3);
        $this->assertSame(3, DB::table('outbox_delivery_attempts')->where('status', 'published')->count());
    }

    public function test_failed_delivery_is_redacted_deferred_and_resumes_with_the_same_event_id(): void
    {
        $eventId = $this->insertOutbox('fixture.retry');
        $listenerState = new class
        {
            public bool $shouldFail = true;
        };
        $deliveries = [];
        Event::listen(OutboxMessage::class, function (OutboxMessage $message) use ($listenerState, &$deliveries): void {
            $deliveries[] = $message->eventId;
            if ($listenerState->shouldFail) {
                throw new RuntimeException('secret-like failure detail must not be stored');
            }
        });

        $this->dispatchCommand(1)
            ->expectsOutput('{"scanned":1,"published":0,"already_published":0,"failed":1,"deferred":1,"exhausted":0}')
            ->assertFailed();
        $this->assertDatabaseHas('outbox_events', ['id' => $eventId, 'published_at' => null]);
        $failure = DB::table('outbox_delivery_attempts')->where('outbox_event_id', $eventId)->first();
        $this->assertNotNull($failure);
        $this->assertSame('failed', $failure->status);
        $this->assertSame('DELIVERY_EXCEPTION', $failure->error_code);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $failure->error_fingerprint);
        $this->assertStringNotContainsString('secret-like', json_encode((array) $failure, JSON_THROW_ON_ERROR));

        $this->dispatchCommand(1)
            ->expectsOutput('{"scanned":0,"published":0,"already_published":0,"failed":0,"deferred":1,"exhausted":0}')
            ->assertSuccessful();
        $this->assertSame([$eventId], $deliveries);

        DB::table('outbox_delivery_attempts')->where('outbox_event_id', $eventId)->update(['next_attempt_at' => now()->subSecond()]);
        $listenerState->shouldFail = false;
        $this->dispatchCommand(1)
            ->expectsOutput('{"scanned":1,"published":1,"already_published":0,"failed":0,"deferred":0,"exhausted":0}')
            ->assertSuccessful();
        $this->assertSame([$eventId, $eventId], $deliveries, 'At-least-once retries preserve the event ID for consumer deduplication.');
        $this->assertDatabaseHas('outbox_events', ['id' => $eventId]);
        $this->assertNotNull(DB::table('outbox_events')->where('id', $eventId)->value('published_at'));
        $this->assertSame([1, 2], DB::table('outbox_delivery_attempts')->where('outbox_event_id', $eventId)->orderBy('attempt_number')->pluck('attempt_number')->map(fn (mixed $value): int => (int) $value)->all());
    }

    public function test_attempt_limit_and_command_limit_validation_are_fail_safe(): void
    {
        config(['modrik.outbox.maximum_attempts' => 2]);
        $eventId = $this->insertOutbox('fixture.exhausted');
        Event::listen(OutboxMessage::class, static function (): never {
            throw new RuntimeException('always fails');
        });

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->dispatchCommand(1)->assertFailed();
            DB::table('outbox_delivery_attempts')->where('outbox_event_id', $eventId)->update(['next_attempt_at' => now()->subSecond()]);
        }
        $this->dispatchCommand(1)
            ->expectsOutput('{"scanned":0,"published":0,"already_published":0,"failed":0,"deferred":0,"exhausted":1}')
            ->assertFailed();
        $this->assertDatabaseCount('outbox_delivery_attempts', 2);
        $this->assertNull(DB::table('outbox_events')->where('id', $eventId)->value('published_at'));

        foreach ([0, 501, 'not-an-integer'] as $limit) {
            $this->dispatchCommand($limit)
                ->expectsOutput('The --limit option must be an integer from 1 to 500.')
                ->assertExitCode(2);
        }
    }

    private function insertOutbox(string $eventType): string
    {
        $id = (string) Str::ulid();
        DB::table('outbox_events')->insert([
            'id' => $id,
            'aggregate_type' => 'fixture',
            'aggregate_id' => (string) Str::ulid(),
            'event_type' => $eventType,
            'payload' => json_encode(['opaque_reference' => (string) Str::ulid()], JSON_THROW_ON_ERROR),
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
}

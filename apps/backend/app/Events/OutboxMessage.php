<?php

namespace App\Events;

final readonly class OutboxMessage
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $eventId,
        public string $eventType,
        public string $aggregateType,
        public string $aggregateId,
        public array $payload,
        public string $occurredAt,
    ) {}
}

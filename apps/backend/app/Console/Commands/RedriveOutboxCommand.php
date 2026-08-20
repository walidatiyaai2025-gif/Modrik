<?php

namespace App\Console\Commands;

use App\Services\OutboxRecoveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use JsonException;

class RedriveOutboxCommand extends Command
{
    protected $signature = 'modrik:outbox-redrive
        {event_id : Exhausted unsent outbox event ULID}
        {--request-id= : Stable ULID for this operator recovery action; reuse it on command retry}
        {--confirm= : Must equal REDRIVE-EXHAUSTED}';

    protected $description = 'Explicitly redrive one exhausted unsent outbox event after engineering review';

    /** @throws JsonException */
    public function handle(OutboxRecoveryService $recovery): int
    {
        $eventId = (string) $this->argument('event_id');
        $requestId = (string) $this->option('request-id');

        if (! Str::isUlid($eventId)) {
            $this->error('The event_id argument must be a valid ULID.');

            return self::INVALID;
        }
        if (! Str::isUlid($requestId)) {
            $this->error('The --request-id option must be a valid ULID.');

            return self::INVALID;
        }
        if ($this->option('confirm') !== 'REDRIVE-EXHAUSTED') {
            $this->error('The --confirm option must equal REDRIVE-EXHAUSTED.');

            return self::INVALID;
        }

        $result = $recovery->redrive($eventId, $requestId);
        $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $result['status'] === 'published' ? self::SUCCESS : self::FAILURE;
    }
}

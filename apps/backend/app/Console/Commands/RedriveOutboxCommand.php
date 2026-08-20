<?php

namespace App\Console\Commands;

use App\Services\OutboxRedriveService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use JsonException;

class RedriveOutboxCommand extends Command
{
    protected $signature = 'modrik:outbox-redrive
        {event : Exhausted unsent outbox event ULID}
        {--confirm : Confirm operator-reviewed forward repair}';

    protected $description = 'Explicitly reopen one exhausted unsent outbox event for a bounded redrive cycle';

    /** @throws JsonException */
    public function handle(OutboxRedriveService $redrive): int
    {
        $eventId = (string) $this->argument('event');
        if (! Str::isUlid($eventId)) {
            $this->error('The event argument must be a valid ULID.');

            return self::INVALID;
        }
        if ($this->option('confirm') !== true) {
            $this->error('Explicit --confirm is required after engineering review.');

            return self::INVALID;
        }

        $result = $redrive->requestRedrive($eventId);
        $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return in_array($result['status'], ['redrive_requested', 'already_requested'], true)
            ? self::SUCCESS
            : self::FAILURE;
    }
}

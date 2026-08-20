<?php

namespace App\Console\Commands;

use App\Services\OutboxDispatchService;
use Illuminate\Console\Command;
use JsonException;

class DispatchOutboxCommand extends Command
{
    protected $signature = 'modrik:outbox-dispatch {--limit=100 : Maximum events to inspect in this invocation}';

    protected $description = 'Dispatch one bounded, resumable batch from the transactional outbox';

    /** @throws JsonException */
    public function handle(OutboxDispatchService $outbox): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 500],
        ]);
        if (! is_int($limit)) {
            $this->error('The --limit option must be an integer from 1 to 500.');

            return self::INVALID;
        }

        $summary = $outbox->dispatchBatch($limit);
        $this->line(json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $summary['failed'] > 0 || $summary['exhausted'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

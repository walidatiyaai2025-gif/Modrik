<?php

namespace App\Console\Commands;

use App\Services\RuntimeDiagnostics;
use Illuminate\Console\Command;

class PruneRuntimeDiagnosticsCommand extends Command
{
    protected $signature = 'modrik:diagnostics-prune';

    protected $description = 'Apply the owner-configured runtime diagnostic retention boundary when one is explicitly configured';

    public function handle(RuntimeDiagnostics $diagnostics): int
    {
        $retention = config('modrik.observability.retention_days');
        if (! is_int($retention) || $retention < 1) {
            $this->line('{"retention_configured":false,"deleted":0}');

            return self::SUCCESS;
        }

        $deleted = $diagnostics->pruneConfiguredRetention();
        $this->line(json_encode([
            'retention_configured' => true,
            'retention_days' => $retention,
            'deleted' => $deleted,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}

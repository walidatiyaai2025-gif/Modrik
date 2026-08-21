<?php

namespace App\Support\Observability;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DatabaseDiagnosticSink implements DiagnosticSink
{
    public function __construct(private readonly DiagnosticSanitizer $sanitizer) {}

    /** @param array<string, mixed> $event */
    public function write(array $event): void
    {
        $metadata = $this->sanitizer->metadata(is_array($event['metadata'] ?? null) ? $event['metadata'] : []);
        $row = $event;
        $row['metadata'] = $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR);

        try {
            DB::table('runtime_diagnostic_events')->insert($row);
            $this->prune();
        } catch (Throwable) {
            // Diagnostics are optional and must never replace a product result.
        }

        try {
            $severity = (string) ($event['severity'] ?? 'info');
            $level = $severity === 'warn' ? 'warning' : $severity;
            Log::log($level, 'modrik.runtime', [
                'correlation_id' => $event['correlation_id'] ?? null,
                'data_class' => $event['data_class'] ?? null,
                'surface' => $event['surface'] ?? null,
                'category' => $event['category'] ?? null,
                'stable_code' => $event['stable_code'] ?? null,
                'route' => $event['route'] ?? null,
                'action' => $event['action'] ?? null,
                'duration_ms' => $event['duration_ms'] ?? null,
                'environment' => $event['environment'] ?? null,
                'build_identity' => $event['build_identity'] ?? null,
                'metadata' => $metadata,
            ]);
        } catch (Throwable) {
            // Avoid recursive logging failures.
        }
    }

    private function prune(): void
    {
        $maxEvents = max(10, min(50000, (int) config('observability.max_events', 5000)));
        $count = DB::table('runtime_diagnostic_events')->count();
        $excess = $count - $maxEvents;

        if ($excess <= 0) {
            return;
        }

        $ids = DB::table('runtime_diagnostic_events')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit($excess)
            ->pluck('id')
            ->all();

        if ($ids !== []) {
            DB::table('runtime_diagnostic_events')->whereIn('id', $ids)->delete();
        }
    }
}

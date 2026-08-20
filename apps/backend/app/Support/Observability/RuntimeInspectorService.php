<?php

namespace App\Support\Observability;

use App\Support\CorrelationId;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class RuntimeInspectorService
{
    public function __construct(private readonly DiagnosticSanitizer $sanitizer) {}

    public function isAvailable(): bool
    {
        if (! (bool) config('observability.inspector_enabled', false)) {
            return false;
        }

        try {
            return Schema::hasTable('runtime_diagnostic_events');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function events(array $filters = [], ?int $limit = null): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        try {
            $queryLimit = $limit ?? (int) config('observability.query_limit', 100);
            $queryLimit = max(1, min(200, $queryLimit));
            $rows = $this->filteredQuery($filters)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit($queryLimit)
                ->get();

            return $rows->map(fn (object $row): array => $this->serializeRow($row))->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{json: string, event_count: int, truncated: bool}
     */
    public function exportBundle(array $filters = []): array
    {
        $maxEvents = max(1, min(200, (int) config('observability.export_max_events', 100)));
        $maxBytes = max(4096, min(1048576, (int) config('observability.export_max_bytes', 262144)));
        $events = $this->events($filters, $maxEvents);
        $originalCount = count($events);
        $truncated = false;

        do {
            $bundle = [
                'schema' => 'modrik.runtime-diagnostics.v1',
                'generated_at' => now('UTC')->toIso8601String(),
                'summary' => $this->runtimeSummary(),
                'filters' => $this->safeFilters($filters),
                'events' => $events,
                'truncated' => $truncated,
            ];
            $json = json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            if (strlen($json) <= $maxBytes || $events === []) {
                break;
            }

            array_pop($events);
            $truncated = true;
        } while (true);

        return [
            'json' => $json,
            'event_count' => count($events),
            'truncated' => $truncated || count($events) < $originalCount,
        ];
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public function runtimeSummary(): array
    {
        return [
            'environment' => (string) app()->environment(),
            'framework' => app()->version(),
            'php' => PHP_VERSION,
            'build_identity' => is_string(config('observability.build_identity'))
                ? $this->sanitizer->safeCode((string) config('observability.build_identity'), 96)
                : null,
            'diagnostics_enabled' => (bool) config('observability.enabled', true),
            'inspector_enabled' => (bool) config('observability.inspector_enabled', false),
            'diagnostic_events' => $this->safeCount('runtime_diagnostic_events'),
            'outbox_events' => $this->safeCount('outbox_events'),
            'outbox_delivery_attempts' => $this->safeCount('outbox_delivery_attempts'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = DB::table('runtime_diagnostic_events');
        $hours = max(1, min(168, (int) ($filters['hours'] ?? 24)));
        $query->where('occurred_at', '>=', now('UTC')->subHours($hours));

        $correlationId = is_string($filters['correlation_id'] ?? null) ? trim($filters['correlation_id']) : '';
        if ($correlationId !== '' && CorrelationId::isValid($correlationId)) {
            $query->where('correlation_id', $correlationId);
        }

        $severity = is_string($filters['severity'] ?? null) ? $filters['severity'] : '';
        if (in_array($severity, ['debug', 'info', 'warn', 'error', 'critical'], true)) {
            $query->where('severity', $severity);
        }

        $surface = is_string($filters['surface'] ?? null) ? $filters['surface'] : '';
        if (in_array($surface, ['backend', 'admin', 'web', 'public', 'mobile'], true)) {
            $query->where('surface', $surface);
        }

        $dataClass = is_string($filters['data_class'] ?? null) ? $filters['data_class'] : '';
        if (in_array($dataClass, ['application_log', 'audit'], true)) {
            $query->where('data_class', $dataClass);
        }

        $stableCode = is_string($filters['stable_code'] ?? null)
            ? $this->sanitizer->safeCode(trim($filters['stable_code']))
            : null;
        if ($stableCode !== null) {
            $query->where('stable_code', $stableCode);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRow(object $row): array
    {
        $metadata = [];
        if (is_string($row->metadata ?? null) && $row->metadata !== '') {
            $decoded = json_decode($row->metadata, true);
            $metadata = is_array($decoded) ? $this->sanitizer->metadata($decoded) : [];
        }

        return [
            'occurred_at' => (string) $row->occurred_at,
            'correlation_id' => (string) $row->correlation_id,
            'data_class' => (string) $row->data_class,
            'severity' => (string) $row->severity,
            'surface' => (string) $row->surface,
            'category' => (string) $row->category,
            'stable_code' => $row->stable_code !== null ? (string) $row->stable_code : null,
            'route' => $row->route !== null ? (string) $row->route : null,
            'action' => $row->action !== null ? (string) $row->action : null,
            'duration_ms' => $row->duration_ms !== null ? (int) $row->duration_ms : null,
            'environment' => $row->environment !== null ? (string) $row->environment : null,
            'build_identity' => $row->build_identity !== null ? (string) $row->build_identity : null,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int|string>
     */
    private function safeFilters(array $filters): array
    {
        $safe = ['hours' => max(1, min(168, (int) ($filters['hours'] ?? 24)))];
        foreach (['severity', 'surface', 'data_class'] as $key) {
            if (is_string($filters[$key] ?? null)) {
                $safe[$key] = mb_substr((string) $filters[$key], 0, 24);
            }
        }

        if (is_string($filters['stable_code'] ?? null)) {
            $code = $this->sanitizer->safeCode((string) $filters['stable_code']);
            if ($code !== null) {
                $safe['stable_code'] = $code;
            }
        }

        if (is_string($filters['correlation_id'] ?? null) && CorrelationId::isValid((string) $filters['correlation_id'])) {
            $safe['correlation_id'] = (string) $filters['correlation_id'];
        }

        return $safe;
    }

    private function safeCount(string $table): int
    {
        try {
            return Schema::hasTable($table) ? DB::table($table)->count() : 0;
        } catch (Throwable) {
            return 0;
        }
    }
}

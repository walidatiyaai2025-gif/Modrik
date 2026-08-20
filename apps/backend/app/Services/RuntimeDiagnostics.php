<?php

namespace App\Services;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use App\Support\CorrelationId;
use App\Support\DiagnosticSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class RuntimeDiagnostics
{
    /** @var list<string> */
    private const SEVERITIES = ['debug', 'info', 'warn', 'error', 'critical'];

    /** @var list<string> */
    private const SURFACES = ['backend', 'admin'];

    /** @var list<string> */
    private const EVENT_CLASSES = ['application_log', 'durable_audit'];

    public function recordRequest(Request $request, Response $response, int $durationMs): void
    {
        $status = $response->getStatusCode();

        $this->record([
            'event_class' => 'application_log',
            'severity' => $status >= 500 ? 'error' : ($status >= 400 ? 'warn' : 'info'),
            'surface' => $this->surface($request),
            'category' => 'http_request',
            'correlation_id' => CorrelationId::assign($request),
            'route_name' => $this->routeName($request),
            'stable_code' => $this->stableCodeFromResponse($response),
            'outcome' => $this->outcome($status),
            'status_code' => $status,
            'duration_ms' => max(0, $durationMs),
            'actor_id' => $this->actorId($request),
            'metadata' => [
                'event_name' => 'request.completed',
                'http_method' => $request->getMethod(),
                'response_class' => $this->responseClass($status),
            ],
        ]);
    }

    public function recordException(Request $request, Throwable $exception, int $durationMs): void
    {
        $status = $exception instanceof ApiProblemException
            ? $exception->status
            : ($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500);
        $stableCode = $exception instanceof ApiProblemException
            ? $exception->problemCode
            : ($exception instanceof HttpExceptionInterface ? 'HTTP_'.$status : 'UNHANDLED_EXCEPTION');

        $this->record([
            'event_class' => 'application_log',
            'severity' => $status >= 500 ? 'error' : 'warn',
            'surface' => $this->surface($request),
            'category' => 'exception',
            'correlation_id' => CorrelationId::assign($request),
            'route_name' => $this->routeName($request),
            'stable_code' => $stableCode,
            'outcome' => $this->outcome($status),
            'status_code' => $status,
            'duration_ms' => max(0, $durationMs),
            'actor_id' => $this->actorId($request),
            'metadata' => [
                'event_name' => 'request.exception',
                'http_method' => $request->getMethod(),
                'response_class' => $this->responseClass($status),
                'exception_class' => $exception::class,
                'fingerprint' => hash('sha256', $exception::class.'|'.(string) $exception->getCode()),
            ],
        ]);
    }

    /** @param array<string, mixed> $metadata */
    public function recordAudit(
        string $category,
        string $correlationId,
        ?User $actor = null,
        array $metadata = [],
    ): void {
        if (! CorrelationId::isValid($correlationId)) {
            return;
        }

        $this->record([
            'event_class' => 'durable_audit',
            'severity' => 'info',
            'surface' => 'admin',
            'category' => $category,
            'correlation_id' => $correlationId,
            'route_name' => null,
            'stable_code' => null,
            'outcome' => 'recorded',
            'status_code' => null,
            'duration_ms' => null,
            'actor_id' => $actor === null ? null : (string) $actor->getKey(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * @param  array{correlation_id?: string, severity?: string, surface?: string, stable_code?: string, event_class?: string, window_minutes?: int}  $filters
     * @return list<array<string, mixed>>
     */
    public function events(array $filters = [], ?int $limit = null): array
    {
        if (! $this->storageAvailable()) {
            return [];
        }

        $normalized = $this->normalizedFilters($filters);
        $boundedLimit = max(1, min(
            $limit ?? $this->maximumQueryEvents(),
            $this->maximumQueryEvents(),
        ));

        try {
            $query = DB::table('runtime_diagnostic_events')
                ->where('recorded_at', '>=', now()->subMinutes($normalized['window_minutes']));

            if ($normalized['correlation_id'] !== null) {
                $query->where('correlation_id', $normalized['correlation_id']);
            }
            if ($normalized['severity'] !== null) {
                $query->where('severity', $normalized['severity']);
            }
            if ($normalized['surface'] !== null) {
                $query->where('surface', $normalized['surface']);
            }
            if ($normalized['stable_code'] !== null) {
                $query->where('stable_code', $normalized['stable_code']);
            }
            if ($normalized['event_class'] !== null) {
                $query->where('event_class', $normalized['event_class']);
            }

            $events = $query
                ->orderByDesc('recorded_at')
                ->orderByDesc('id')
                ->limit($boundedLimit)
                ->get()
                ->map(fn (object $row): array => $this->sanitizeStoredRow($row))
                ->all();

            return array_values($events);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array{correlation_id?: string, severity?: string, surface?: string, stable_code?: string, event_class?: string, window_minutes?: int}  $filters
     */
    public function exportJson(array $filters, string $correlationId, ?User $actor = null): string
    {
        $normalized = $this->normalizedFilters($filters);
        $events = $this->events($filters, $this->maximumExportEvents());
        $bundle = [
            'schema_version' => '1.0',
            'generated_at' => now()->utc()->toIso8601String(),
            'truncated' => false,
            'runtime' => $this->runtimeSummary(),
            'filters' => array_filter($normalized, static fn (mixed $value): bool => $value !== null),
            'events' => $events,
            'outbox_recovery' => $this->outboxSummary(),
        ];

        $maximumBytes = $this->maximumExportBytes();
        $json = $this->encode($bundle);
        while (strlen($json) > $maximumBytes && $bundle['events'] !== []) {
            array_pop($bundle['events']);
            $bundle['truncated'] = true;
            $json = $this->encode($bundle);
        }

        if (strlen($json) > $maximumBytes) {
            $bundle['events'] = [];
            $bundle['outbox_recovery'] = ['available' => false, 'reason' => 'export_size_limit'];
            $bundle['truncated'] = true;
            $json = $this->encode($bundle);
        }

        $this->recordAudit('diagnostic_export', $correlationId, $actor, [
            'event_name' => 'diagnostic.exported',
            'event_count' => count($bundle['events']),
            'filter_count' => count(array_filter($normalized, static fn (mixed $value): bool => $value !== null)),
            'window_minutes' => $normalized['window_minutes'],
            'export_bytes' => strlen($json),
        ]);

        return $json;
    }

    /** @return array<string, bool|int|string|null> */
    public function runtimeSummary(): array
    {
        $retention = config('modrik.observability.retention_days');

        return [
            'environment' => $this->environment(),
            'build_ref' => $this->buildRef(),
            'release_version' => DiagnosticSanitizer::text(config('modrik.observability.release_version'), 64),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database_driver' => DiagnosticSanitizer::text(config('database.default'), 32),
            'storage_enabled' => (bool) config('modrik.observability.storage_enabled', true),
            'storage_available' => $this->storageAvailable(),
            'retention_days' => is_int($retention) ? $retention : null,
        ];
    }

    /** @return array<string, mixed> */
    public function outboxSummary(): array
    {
        try {
            if (! Schema::hasTable('outbox_events')) {
                return ['available' => false];
            }

            $attemptStatuses = [];
            if (Schema::hasTable('outbox_delivery_attempts')) {
                foreach (DB::table('outbox_delivery_attempts')
                    ->selectRaw('status, COUNT(*) as total')
                    ->groupBy('status')
                    ->orderBy('status')
                    ->get() as $row) {
                    $status = DiagnosticSanitizer::text($row->status ?? null, 24);
                    if ($status !== null) {
                        $attemptStatuses[$status] = (int) ($row->total ?? 0);
                    }
                }
            }

            return [
                'available' => true,
                'pending_events' => DB::table('outbox_events')->whereNull('published_at')->count(),
                'published_events' => DB::table('outbox_events')->whereNotNull('published_at')->count(),
                'delivery_attempts' => $attemptStatuses,
            ];
        } catch (Throwable) {
            return ['available' => false];
        }
    }

    public function pruneConfiguredRetention(): int
    {
        $retention = config('modrik.observability.retention_days');
        if (! is_int($retention) || $retention < 1 || ! $this->storageAvailable()) {
            return 0;
        }

        try {
            return DB::table('runtime_diagnostic_events')
                ->where('recorded_at', '<', now()->subDays($retention))
                ->delete();
        } catch (Throwable) {
            return 0;
        }
    }

    public function storageAvailable(): bool
    {
        if (! (bool) config('modrik.observability.storage_enabled', true)) {
            return false;
        }

        try {
            return Schema::hasTable('runtime_diagnostic_events');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array{
     *   event_class: string,
     *   severity: string,
     *   surface: string,
     *   category: string,
     *   correlation_id: string,
     *   route_name: string|null,
     *   stable_code: string|null,
     *   outcome: string,
     *   status_code: int|null,
     *   duration_ms: int|null,
     *   actor_id: string|null,
     *   metadata: array<string, mixed>
     * } $event
     */
    private function record(array $event): void
    {
        if (! (bool) config('modrik.observability.enabled', true)
            || ! in_array($event['event_class'], self::EVENT_CLASSES, true)
            || ! in_array($event['severity'], self::SEVERITIES, true)
            || ! in_array($event['surface'], self::SURFACES, true)
            || ! CorrelationId::isValid($event['correlation_id'])) {
            return;
        }

        $metadata = DiagnosticSanitizer::metadata($event['metadata']);
        $metadataJson = $this->metadataJson($metadata);
        if ($metadata !== [] && $metadataJson === null) {
            $metadata = [];
        }
        $category = DiagnosticSanitizer::text($event['category'], 64) ?? 'runtime';
        $routeName = DiagnosticSanitizer::text($event['route_name'], 160);
        $stableCode = DiagnosticSanitizer::stableCode($event['stable_code']);
        $outcome = DiagnosticSanitizer::text($event['outcome'], 32) ?? 'unknown';
        $actorId = DiagnosticSanitizer::text($event['actor_id'], 26);
        $recordedAt = now()->utc();

        $context = [
            'event_class' => $event['event_class'],
            'severity' => $event['severity'],
            'surface' => $event['surface'],
            'category' => $category,
            'correlation_id' => $event['correlation_id'],
            'route_name' => $routeName,
            'stable_code' => $stableCode,
            'outcome' => $outcome,
            'status_code' => $event['status_code'],
            'duration_ms' => $event['duration_ms'],
            'environment' => $this->environment(),
            'build_ref' => $this->buildRef(),
            'actor_id' => $actorId,
            'metadata' => $metadata,
        ];

        try {
            Log::log($event['severity'], 'modrik.runtime_event', $context);
        } catch (Throwable) {
            // Observability must never break the authoritative business flow.
        }

        if (! (bool) config('modrik.observability.storage_enabled', true)) {
            return;
        }

        try {
            if (! Schema::hasTable('runtime_diagnostic_events')) {
                return;
            }

            DB::table('runtime_diagnostic_events')->insert([
                'id' => (string) Str::ulid(),
                'event_class' => $event['event_class'],
                'severity' => $event['severity'],
                'surface' => $event['surface'],
                'category' => $category,
                'correlation_id' => $event['correlation_id'],
                'route_name' => $routeName,
                'stable_code' => $stableCode,
                'outcome' => $outcome,
                'status_code' => $event['status_code'],
                'duration_ms' => $event['duration_ms'],
                'environment' => $this->environment(),
                'build_ref' => $this->buildRef(),
                'actor_id' => $actorId,
                'metadata' => $metadataJson,
                'recorded_at' => $recordedAt,
            ]);
        } catch (Throwable) {
            // Database diagnostics are fail-open by contract.
        }
    }

    /**
     * @param  array{correlation_id?: string, severity?: string, surface?: string, stable_code?: string, event_class?: string, window_minutes?: int}  $filters
     * @return array{correlation_id: string|null, severity: string|null, surface: string|null, stable_code: string|null, event_class: string|null, window_minutes: int}
     */
    private function normalizedFilters(array $filters): array
    {
        $correlationId = $filters['correlation_id'] ?? null;
        $severity = $filters['severity'] ?? null;
        $surface = $filters['surface'] ?? null;
        $stableCode = $filters['stable_code'] ?? null;
        $eventClass = $filters['event_class'] ?? null;
        $windowMinutes = $filters['window_minutes'] ?? 60;

        return [
            'correlation_id' => is_string($correlationId) && CorrelationId::isValid($correlationId) ? $correlationId : null,
            'severity' => is_string($severity) && in_array($severity, self::SEVERITIES, true) ? $severity : null,
            'surface' => is_string($surface) && in_array($surface, self::SURFACES, true) ? $surface : null,
            'stable_code' => DiagnosticSanitizer::stableCode($stableCode),
            'event_class' => is_string($eventClass) && in_array($eventClass, self::EVENT_CLASSES, true) ? $eventClass : null,
            'window_minutes' => max(5, min((int) $windowMinutes, 10_080)),
        ];
    }

    /** @return array<string, mixed> */
    private function sanitizeStoredRow(object $row): array
    {
        $metadata = [];
        $rawMetadata = $row->metadata ?? null;
        if (is_string($rawMetadata) && $rawMetadata !== '') {
            $decoded = json_decode($rawMetadata, true);
            if (is_array($decoded)) {
                $metadata = DiagnosticSanitizer::metadata($decoded);
            }
        } elseif (is_array($rawMetadata)) {
            $metadata = DiagnosticSanitizer::metadata($rawMetadata);
        }

        return [
            'id' => DiagnosticSanitizer::text($row->id ?? null, 26),
            'event_class' => DiagnosticSanitizer::text($row->event_class ?? null, 24),
            'severity' => DiagnosticSanitizer::text($row->severity ?? null, 16),
            'surface' => DiagnosticSanitizer::text($row->surface ?? null, 24),
            'category' => DiagnosticSanitizer::text($row->category ?? null, 64),
            'correlation_id' => is_string($row->correlation_id ?? null) && CorrelationId::isValid($row->correlation_id)
                ? $row->correlation_id
                : null,
            'route_name' => DiagnosticSanitizer::text($row->route_name ?? null, 160),
            'stable_code' => DiagnosticSanitizer::stableCode($row->stable_code ?? null),
            'outcome' => DiagnosticSanitizer::text($row->outcome ?? null, 32),
            'status_code' => is_numeric($row->status_code ?? null) ? (int) $row->status_code : null,
            'duration_ms' => is_numeric($row->duration_ms ?? null) ? (int) $row->duration_ms : null,
            'environment' => DiagnosticSanitizer::text($row->environment ?? null, 32),
            'build_ref' => DiagnosticSanitizer::text($row->build_ref ?? null, 128),
            'actor_id' => DiagnosticSanitizer::text($row->actor_id ?? null, 26),
            'metadata' => $metadata,
            'recorded_at' => DiagnosticSanitizer::text((string) ($row->recorded_at ?? ''), 40),
        ];
    }

    /** @param array<string, bool|float|int|string> $metadata */
    private function metadataJson(array $metadata): ?string
    {
        try {
            $json = json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (Throwable) {
            return null;
        }

        $maximumBytes = max(256, min((int) config('modrik.observability.maximum_metadata_bytes', 4_096), 16_384));

        return strlen($json) <= $maximumBytes ? $json : null;
    }

    /** @param array<string, mixed> $bundle */
    private function encode(array $bundle): string
    {
        try {
            return json_encode($bundle, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } catch (Throwable) {
            return '{"schema_version":"1.0","truncated":true,"events":[]}';
        }
    }

    private function stableCodeFromResponse(Response $response): ?string
    {
        $contentType = (string) $response->headers->get('Content-Type', '');
        if (! str_starts_with(strtolower($contentType), 'application/problem+json')) {
            return null;
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '' || strlen($content) > 4_096) {
            return null;
        }

        try {
            $decoded = json_decode($content, true, 8, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? DiagnosticSanitizer::stableCode($decoded['code'] ?? null) : null;
    }

    private function routeName(Request $request): ?string
    {
        $route = $request->route();
        if (! is_object($route) || ! method_exists($route, 'getName')) {
            return null;
        }

        return DiagnosticSanitizer::text($route->getName(), 160);
    }

    private function actorId(Request $request): ?string
    {
        $user = $request->user();

        return $user instanceof User ? (string) $user->getKey() : null;
    }

    private function surface(Request $request): string
    {
        return $request->is('admin', 'admin/*') ? 'admin' : 'backend';
    }

    private function outcome(int $status): string
    {
        if ($status >= 500) {
            return 'server_error';
        }
        if ($status >= 400) {
            return 'client_error';
        }

        return 'success';
    }

    private function responseClass(int $status): string
    {
        return intdiv(max(100, min($status, 599)), 100).'xx';
    }

    private function environment(): string
    {
        return DiagnosticSanitizer::text(config('app.env'), 32) ?? 'unknown';
    }

    private function buildRef(): ?string
    {
        return DiagnosticSanitizer::text(config('modrik.observability.build_ref'), 128);
    }

    private function maximumQueryEvents(): int
    {
        return max(1, min((int) config('modrik.observability.maximum_query_events', 200), 1_000));
    }

    private function maximumExportEvents(): int
    {
        return max(1, min((int) config('modrik.observability.maximum_export_events', 500), 2_000));
    }

    private function maximumExportBytes(): int
    {
        return max(16_384, min((int) config('modrik.observability.maximum_export_bytes', 262_144), 1_048_576));
    }
}

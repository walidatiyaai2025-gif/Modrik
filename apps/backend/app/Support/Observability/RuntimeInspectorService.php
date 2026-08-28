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

            return array_values($rows->map(fn (object $row): array => $this->serializeRow($row))->all());
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

    /** @return array<string, mixed> */
    public function runtimeSummary(): array
    {
        $viewPaths = array_values(array_filter(
            (array) config('view.paths', []),
            static fn (mixed $path): bool => is_string($path) && $path !== '',
        ));
        $staleViewPath = false;
        foreach ($viewPaths as $path) {
            if (str_contains(str_replace('\\', '/', $path), '.modrik-updates/releases/')) {
                $staleViewPath = true;
                break;
            }
        }

        $backendRelease = $this->safeReleaseSha(base_path('RELEASE_SHA.txt'));
        $storageRelease = $this->safeReleaseSha(storage_path('app/modrik-release.txt'));
        $webRoot = rtrim((string) config('updates.live_web_root', dirname(base_path()).DIRECTORY_SEPARATOR.'demo.modrik.org'), DIRECTORY_SEPARATOR);
        $webRelease = $this->safeReleaseSha($webRoot.DIRECTORY_SEPARATOR.'RELEASE_SHA.txt');
        $restartMarker = $webRoot.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'restart.txt';

        $dbOk = false;
        $dbLatencyMs = null;
        $dbError = null;
        $started = microtime(true);
        try {
            DB::select('select 1 as modrik_runtime_probe');
            $dbOk = true;
            $dbLatencyMs = (int) round((microtime(true) - $started) * 1000);
        } catch (Throwable $exception) {
            $dbError = $this->sanitizer->safeCode(class_basename($exception), 64);
        }

        $mail = $this->mailRuntime();
        $storageWritable = is_dir(storage_path()) && is_writable(storage_path());
        $bootstrapCachePath = base_path('bootstrap/cache');
        $bootstrapCacheWritable = is_dir($bootstrapCachePath) && is_writable($bootstrapCachePath);

        $reasons = [];
        $status = 'ok';
        if ($staleViewPath) {
            $status = 'fail';
            $reasons[] = 'Laravel view.paths still references a staged .modrik-updates release.';
        }
        if (! $dbOk) {
            $status = 'fail';
            $reasons[] = 'Database read probe failed.';
        }
        if (! $storageWritable) {
            $status = 'fail';
            $reasons[] = 'Laravel storage path is not writable.';
        }
        if (! $bootstrapCacheWritable) {
            $status = 'fail';
            $reasons[] = 'bootstrap/cache is not writable.';
        }
        if ($backendRelease !== null && $storageRelease !== null && ! hash_equals($backendRelease, $storageRelease)) {
            if ($status === 'ok') {
                $status = 'warn';
            }
            $reasons[] = 'Backend RELEASE_SHA and durable release identity do not match.';
        }
        if ($backendRelease !== null && $webRelease !== null && ! hash_equals($backendRelease, $webRelease)) {
            if ($status === 'ok') {
                $status = 'warn';
            }
            $reasons[] = 'Backend and Web release identities do not match.';
        }

        return [
            'runtime_status' => $status,
            'runtime_reasons' => $reasons,
            'environment' => (string) app()->environment(),
            'debug' => (bool) config('app.debug', false),
            'framework' => app()->version(),
            'php' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'build_identity' => is_string(config('observability.build_identity'))
                ? $this->sanitizer->safeCode((string) config('observability.build_identity'), 96)
                : null,
            'backend_release_sha' => $backendRelease,
            'durable_release_sha' => $storageRelease,
            'web_release_sha' => $webRelease,
            'web_restart_marker_at' => $this->safeFileTimestamp($restartMarker),
            'base_path' => base_path(),
            'resource_views_path' => resource_path('views'),
            'view_paths' => $viewPaths,
            'view_path_status' => $staleViewPath ? 'fail' : 'ok',
            'stale_view_path' => $staleViewPath,
            'config_cached' => app()->configurationIsCached(),
            'route_cached' => app()->routesAreCached(),
            'config_cache_path' => base_path('bootstrap/cache/config.php'),
            'storage_writable' => $storageWritable,
            'bootstrap_cache_writable' => $bootstrapCacheWritable,
            'db_driver' => (string) config('database.default', ''),
            'db_ok' => $dbOk,
            'db_latency_ms' => $dbLatencyMs,
            'db_error_code' => $dbError,
            'cache_store' => (string) config('cache.default', ''),
            'session_driver' => (string) config('session.driver', ''),
            'queue_connection' => (string) config('queue.default', ''),
            'mail_source' => $mail['source'],
            'enabled_smtp_providers' => $mail['enabled_count'],
            'active_smtp_provider' => $mail['provider'],
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

    /** @return array<string, mixed> */
    private function serializeRow(object $row): array
    {
        /** @var array<string, mixed> $values */
        $values = (array) $row;
        $metadata = [];
        $rawMetadata = $values['metadata'] ?? null;
        if (is_string($rawMetadata) && $rawMetadata !== '') {
            $decoded = json_decode($rawMetadata, true);
            $metadata = is_array($decoded) ? $this->sanitizer->metadata($decoded) : [];
        }

        return [
            'occurred_at' => (string) ($values['occurred_at'] ?? ''),
            'correlation_id' => (string) ($values['correlation_id'] ?? ''),
            'data_class' => (string) ($values['data_class'] ?? ''),
            'severity' => (string) ($values['severity'] ?? ''),
            'surface' => (string) ($values['surface'] ?? ''),
            'category' => (string) ($values['category'] ?? ''),
            'stable_code' => isset($values['stable_code']) ? (string) $values['stable_code'] : null,
            'route' => isset($values['route']) ? (string) $values['route'] : null,
            'action' => isset($values['action']) ? (string) $values['action'] : null,
            'duration_ms' => isset($values['duration_ms']) ? (int) $values['duration_ms'] : null,
            'environment' => isset($values['environment']) ? (string) $values['environment'] : null,
            'build_identity' => isset($values['build_identity']) ? (string) $values['build_identity'] : null,
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

        return $safe;
    }

    /** @return array{source: string, enabled_count: int, provider: ?array<string, mixed>} */
    private function mailRuntime(): array
    {
        try {
            if (! Schema::hasTable('smtp_providers')) {
                return ['source' => 'environment_fallback', 'enabled_count' => 0, 'provider' => null];
            }

            $enabledCount = DB::table('smtp_providers')->where('is_enabled', true)->count();
            $row = DB::table('smtp_providers')
                ->where('is_enabled', true)
                ->orderBy('name')
                ->first(['id', 'name', 'host', 'port', 'scheme', 'from_address', 'last_test_status', 'last_error_code']);

            if ($row === null) {
                return ['source' => 'environment_fallback', 'enabled_count' => 0, 'provider' => null];
            }

            return [
                'source' => 'managed_smtp_provider_pool',
                'enabled_count' => $enabledCount,
                'provider' => [
                    'id' => (string) $row->id,
                    'name' => (string) $row->name,
                    'host' => (string) $row->host,
                    'port' => (int) $row->port,
                    'security' => ((string) ($row->scheme ?? '')) === 'smtps' ? 'SMTPS' : 'STARTTLS / auto TLS',
                    'from_address' => (string) $row->from_address,
                    'last_test_status' => is_string($row->last_test_status ?? null) ? $row->last_test_status : null,
                    'last_error_code' => is_string($row->last_error_code ?? null) ? $row->last_error_code : null,
                ],
            ];
        } catch (Throwable) {
            return ['source' => 'unknown', 'enabled_count' => 0, 'provider' => null];
        }
    }

    private function safeReleaseSha(string $path): ?string
    {
        if (! is_readable($path)) {
            return null;
        }

        $value = strtolower(trim((string) @file_get_contents($path)));

        return preg_match('/^[0-9a-f]{40}$/', $value) === 1 ? $value : null;
    }

    private function safeFileTimestamp(string $path): ?string
    {
        $timestamp = @filemtime($path);

        return is_int($timestamp) ? gmdate('c', $timestamp) : null;
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

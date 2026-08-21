<?php

namespace App\Support\Observability;

use App\Support\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class DiagnosticRecorder
{
    public function __construct(
        private readonly DiagnosticSink $sink,
        private readonly DiagnosticSanitizer $sanitizer,
    ) {}

    public function requestOutcome(Request $request, Response $response, int $durationMs): void
    {
        if (! $this->enabled()) {
            return;
        }

        $status = $response->getStatusCode();
        $this->writeSafely([
            'id' => (string) Str::ulid(),
            'occurred_at' => now('UTC'),
            'correlation_id' => CorrelationId::forRequest($request),
            'data_class' => 'application_log',
            'severity' => $status >= 500 ? 'error' : ($status >= 400 ? 'warn' : 'info'),
            'surface' => $this->surface($request),
            'category' => 'request_outcome',
            'stable_code' => $this->responseCode($response),
            'route' => $this->safeRoute($request),
            'action' => $this->safeAction($request),
            'duration_ms' => max(0, min($durationMs, 3600000)),
            'environment' => $this->sanitizer->safeCode((string) app()->environment(), 32),
            'build_identity' => $this->sanitizer->safeCode($this->buildIdentity(), 96),
            'actor_id' => $this->actorId($request),
            'metadata' => [
                'method' => $request->getMethod(),
                'status' => $status,
                'response_class' => intdiv($status, 100).'xx',
            ],
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }

    public function exception(Request $request, Throwable $exception, int $durationMs): void
    {
        if (! $this->enabled()) {
            return;
        }

        $class = $exception::class;
        $fingerprint = hash('sha256', $class.'|'.basename($exception->getFile()).'|'.$exception->getLine());

        $this->writeSafely([
            'id' => (string) Str::ulid(),
            'occurred_at' => now('UTC'),
            'correlation_id' => CorrelationId::forRequest($request),
            'data_class' => 'application_log',
            'severity' => 'error',
            'surface' => $this->surface($request),
            'category' => 'unhandled_exception',
            'stable_code' => 'UNHANDLED_EXCEPTION',
            'route' => $this->safeRoute($request),
            'action' => $this->safeAction($request),
            'duration_ms' => max(0, min($durationMs, 3600000)),
            'environment' => $this->sanitizer->safeCode((string) app()->environment(), 32),
            'build_identity' => $this->sanitizer->safeCode($this->buildIdentity(), 96),
            'actor_id' => $this->actorId($request),
            'metadata' => [
                'method' => $request->getMethod(),
                'exception_class' => class_basename($class),
                'exception_fingerprint' => $fingerprint,
            ],
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function audit(
        string $category,
        ?string $actorId,
        array $metadata = [],
        ?string $correlationId = null,
        string $stableCode = 'DIAGNOSTIC_ADMIN_ACTION',
    ): void {
        if (! $this->enabled()) {
            return;
        }

        $request = request();
        $resolvedCorrelationId = $correlationId ?? CorrelationId::forRequest($request);
        if (! CorrelationId::isValid($resolvedCorrelationId)) {
            $resolvedCorrelationId = (string) Str::ulid();
        }

        $this->writeSafely([
            'id' => (string) Str::ulid(),
            'occurred_at' => now('UTC'),
            'correlation_id' => $resolvedCorrelationId,
            'data_class' => 'audit',
            'severity' => 'info',
            'surface' => 'admin',
            'category' => $this->sanitizer->safeCode($category, 64) ?? 'diagnostic_admin_action',
            'stable_code' => $this->sanitizer->safeCode($stableCode, 96) ?? 'DIAGNOSTIC_ADMIN_ACTION',
            'route' => $this->safeRoute($request),
            'action' => $this->safeAction($request),
            'duration_ms' => null,
            'environment' => $this->sanitizer->safeCode((string) app()->environment(), 32),
            'build_identity' => $this->sanitizer->safeCode($this->buildIdentity(), 96),
            'actor_id' => $actorId,
            'metadata' => $metadata,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function writeSafely(array $event): void
    {
        try {
            $this->sink->write($event);
        } catch (Throwable) {
            // The observability path is explicitly fail-open for core product behavior.
        }
    }

    private function enabled(): bool
    {
        return (bool) config('observability.enabled', true);
    }

    private function surface(Request $request): string
    {
        if ($request->is('admin/*') || $request->is('v1/admin/*')) {
            return 'admin';
        }

        return 'backend';
    }

    private function safeRoute(Request $request): ?string
    {
        $name = $request->route()?->getName();

        return is_string($name) ? $this->sanitizer->safeCode($name, 160) : null;
    }

    private function safeAction(Request $request): ?string
    {
        $action = $request->route()?->getActionName();
        if (! is_string($action) || $action === 'Closure') {
            return null;
        }

        return mb_substr(str_replace('\\', '.', $action), 0, 191);
    }

    private function responseCode(Response $response): ?string
    {
        if (! $response instanceof JsonResponse) {
            return null;
        }

        $body = $response->getData(true);
        if (! is_array($body) || ! is_string($body['code'] ?? null)) {
            return null;
        }

        return $this->sanitizer->safeCode($body['code']);
    }

    private function actorId(Request $request): ?string
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $id = $user->getAuthIdentifier();

        return is_string($id) && preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/D', $id) === 1 ? $id : null;
    }

    private function buildIdentity(): ?string
    {
        $value = config('observability.build_identity');

        return is_string($value) ? $value : null;
    }
}

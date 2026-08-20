<?php

namespace App\Http\Middleware;

use App\Services\RuntimeDiagnostics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class CaptureRuntimeDiagnostics
{
    public function __construct(private readonly RuntimeDiagnostics $diagnostics) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);

        try {
            $response = $next($request);
            $this->diagnostics->recordRequest($request, $response, $this->elapsedMilliseconds($startedAt));

            return $response;
        } catch (Throwable $exception) {
            $this->diagnostics->recordException($request, $exception, $this->elapsedMilliseconds($startedAt));

            throw $exception;
        }
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        $elapsed = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        return max(0, min($elapsed, 4_294_967_295));
    }
}

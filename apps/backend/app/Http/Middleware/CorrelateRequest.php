<?php

namespace App\Http\Middleware;

use App\Support\CorrelationId;
use App\Support\Observability\DiagnosticRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CorrelateRequest
{
    public function __construct(private readonly DiagnosticRecorder $recorder) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = CorrelationId::forRequest($request);
        $startedAt = hrtime(true);
        $response = $next($request);
        $response->headers->set(CorrelationId::HEADER, $correlationId);
        $this->recorder->requestOutcome($request, $response, $this->durationMs($startedAt));

        return $response;
    }

    private function durationMs(int $startedAt): int
    {
        return (int) max(0, min(3600000, intdiv(hrtime(true) - $startedAt, 1000000)));
    }
}

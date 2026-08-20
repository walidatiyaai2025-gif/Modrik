<?php

namespace App\Http\Middleware;

use App\Support\CorrelationId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AssignCorrelationId
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = CorrelationId::assign($request);
        $response = $next($request);
        $response->headers->set(CorrelationId::HEADER, $correlationId);

        return $response;
    }
}

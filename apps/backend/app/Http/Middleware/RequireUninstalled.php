<?php

namespace App\Http\Middleware;

use App\Services\InstallationStateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireUninstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(app(InstallationStateService::class)->installed(), 404);
        return $next($request);
    }
}

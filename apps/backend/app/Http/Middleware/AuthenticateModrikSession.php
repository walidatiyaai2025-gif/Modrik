<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use App\Services\AuthLifecycleService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateModrikSession
{
    public function __construct(private readonly AuthLifecycleService $auth) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        ApiResponse::requestId($request);
        $production = $this->auth->authenticateProductionToken($request->bearerToken());
        if ($production !== null) {
            $request->setUserResolver(static fn (): User => $production['user']);
            $request->attributes->set('auth_session_id', $production['session_id']);
            $request->attributes->set('auth_mode', 'production');

            return $next($request);
        }

        return ApiResponse::problem($request, new ApiProblemException(
            401,
            'AUTHENTICATION_REQUIRED',
            'Authentication required',
            'A valid authenticated session is required.',
        ));
    }
}

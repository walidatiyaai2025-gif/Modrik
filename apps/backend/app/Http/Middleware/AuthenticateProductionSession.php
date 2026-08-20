<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use App\Services\AuthLifecycleService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateProductionSession
{
    public function __construct(private readonly AuthLifecycleService $auth) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        ApiResponse::requestId($request);
        $resolved = $this->auth->authenticateProductionToken($request->bearerToken());
        if ($resolved === null) {
            return ApiResponse::problem($request, new ApiProblemException(
                401,
                'AUTHENTICATION_REQUIRED',
                'Authentication required',
                'A valid production account session is required.',
            ));
        }

        $request->setUserResolver(static fn (): User => $resolved['user']);
        $request->attributes->set('auth_session_id', $resolved['session_id']);
        $request->attributes->set('auth_mode', 'production');

        return $next($request);
    }
}

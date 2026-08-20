<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use App\Services\AuthLifecycleService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireRecentAuthentication
{
    public function __construct(private readonly AuthLifecycleService $auth) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $sessionId = $request->attributes->get('auth_session_id');
        if (! $user instanceof User
            || ! is_string($sessionId)
            || ! $this->auth->isRecentSession($user, $sessionId)) {
            return ApiResponse::problem($request, new ApiProblemException(
                403,
                'RECENT_AUTHENTICATION_REQUIRED',
                'Recent authentication required',
                'Reauthenticate before performing this sensitive account action.',
            ));
        }

        return $next($request);
    }
}

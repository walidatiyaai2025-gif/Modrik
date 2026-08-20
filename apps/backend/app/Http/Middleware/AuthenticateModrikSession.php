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

        $expected = (string) config('modrik.fixture.bearer_token');
        $provided = $request->bearerToken();
        if ((bool) config('modrik.fixture.enabled')
            && $expected !== ''
            && is_string($provided)
            && hash_equals($expected, $provided)) {
            $user = User::query()->find((string) config('modrik.fixture.user_id'));
            if ($user instanceof User) {
                $request->setUserResolver(static fn (): User => $user);
                $request->attributes->set('auth_mode', 'fixture');

                return $next($request);
            }
        }

        return ApiResponse::problem($request, new ApiProblemException(
            401,
            'AUTHENTICATION_REQUIRED',
            'Authentication required',
            'A valid authenticated session is required.',
        ));
    }
}

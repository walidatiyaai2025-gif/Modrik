<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FixtureBearerAuthentication
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        ApiResponse::requestId($request);
        $expected = (string) config('modrik.fixture.bearer_token');
        $provided = $request->bearerToken();

        if (! (bool) config('modrik.fixture.enabled')
            || $expected === ''
            || ! is_string($provided)
            || ! hash_equals($expected, $provided)) {
            return ApiResponse::problem($request, new ApiProblemException(
                status: 401,
                problemCode: 'AUTHENTICATION_REQUIRED',
                problemTitle: 'Authentication required',
                detail: 'A valid authenticated learning session is required.',
            ));
        }

        $user = User::query()->find((string) config('modrik.fixture.user_id'));
        if (! $user instanceof User) {
            return ApiResponse::problem($request, new ApiProblemException(
                status: 401,
                problemCode: 'AUTHENTICATION_REQUIRED',
                problemTitle: 'Authentication required',
                detail: 'The fixture learning profile is unavailable.',
            ));
        }

        $request->setUserResolver(static fn (): User => $user);

        return $next($request);
    }
}

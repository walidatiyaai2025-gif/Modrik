<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireVerifiedEmailForPasswordAccount
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::problem($request, new ApiProblemException(
                401,
                'AUTHENTICATION_REQUIRED',
                'Authentication required',
                'A valid authenticated session is required.',
            ));
        }

        if ((bool) $user->getAttribute('password_enabled') && $user->getAttribute('email_verified_at') === null) {
            return ApiResponse::problem($request, new ApiProblemException(
                403,
                'EMAIL_VERIFICATION_REQUIRED',
                'Email verification required',
                'Verify the account email before performing protected learning mutations.',
            ));
        }

        return $next($request);
    }
}

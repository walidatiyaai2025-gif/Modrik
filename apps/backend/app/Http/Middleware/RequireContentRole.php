<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireContentRole
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! in_array($user->role, ['content_team', 'admin'], true)) {
            return ApiResponse::problem($request, new ApiProblemException(
                403,
                'CONTENT_ROLE_REQUIRED',
                'Content role required',
                'Only an authorized Content Team or Admin account may manage official preparation workflows.',
            ));
        }

        return $next($request);
    }
}

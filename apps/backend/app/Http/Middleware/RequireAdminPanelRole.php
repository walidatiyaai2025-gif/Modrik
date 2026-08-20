<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireAdminPanelRole
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! in_array((string) $user->role, ['admin', 'content_team'], true)) {
            abort(403, 'Only an authorized Admin or Content Team account may access the MODRIK content operations panel.');
        }

        return $next($request);
    }
}

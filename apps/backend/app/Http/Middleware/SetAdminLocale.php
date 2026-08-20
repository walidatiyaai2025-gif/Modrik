<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetAdminLocale
{
    private const SUPPORTED = ['ar', 'en', 'fr'];

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $sessionLocale = $request->session()->get('admin_locale');
        $user = $request->user();
        $userLocale = $user instanceof User ? (string) $user->locale : '';
        $locale = is_string($sessionLocale) ? $sessionLocale : $userLocale;
        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'en';
        }
        App::setLocale($locale);

        return $next($request);
    }
}

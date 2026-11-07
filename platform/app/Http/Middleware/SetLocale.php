<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Dil: hesabdakı seçim > ?lang= > cookie > az. Brauzer dilinə görə avtomatik keçid yoxdur. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = config('platform.locales');
        $q = $request->query('lang');
        $locale = Auth::user()?->locale;
        if (is_string($q) && in_array($q, $allowed, true)) {
            $locale = $q;
            if (Auth::check() && Auth::user()->locale !== $q) {
                Auth::user()->forceFill(['locale' => $q])->save();
            }
        }
        $locale ??= $request->cookie('ms_lang');
        $locale = in_array($locale, $allowed, true) ? $locale : 'az';
        app()->setLocale($locale);
        $response = $next($request);
        if (is_string($q) && in_array($q, $allowed, true) && method_exists($response, 'withCookie')) {
            $response->withCookie(cookie('ms_lang', $q, 60 * 24 * 365, '/platform', null, config('session.secure'), true, false, 'lax'));
        }

        return $response;
    }
}

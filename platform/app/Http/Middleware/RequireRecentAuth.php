<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Həssas əməliyyatlar (parol, 2FA, billing parametrləri) son 15 dəqiqədə parol təsdiqi tələb edir. */
class RequireRecentAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $s = $request->attributes->get('user_session');
        $at = $s?->reauth_at ?? $s?->created_at;
        if (! $at || $at->lt(now()->subMinutes(config('platform.auth.recent_auth_minutes')))) {
            if ($request->expectsJson()) {
                abort(403, 'reauth_required');
            }
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('reauth');
        }

        return $next($request);
    }
}

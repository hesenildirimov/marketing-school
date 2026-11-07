<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Admin və təlimçi üçün TOTP məcburidir: 2FA keçilməyibsə yalnız 2FA ekranları açıqdır. */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $s = $request->attributes->get('user_session');
        if ($user && $user->isStaff() && (! $s || ! $s->two_factor_at)) {
            if ($request->expectsJson()) {
                abort(403, 'two_factor_required');
            }

            return redirect()->route('2fa.challenge');
        }

        return $next($request);
    }
}

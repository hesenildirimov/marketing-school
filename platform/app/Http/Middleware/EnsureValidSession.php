<?php

namespace App\Http\Middleware;

use App\Domain\Auth\SessionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Server-side sessiya etibarlı olmalıdır (ləğv edilməmiş, cihaz limiti, staff üçün hərəkətsizlik limiti). */
class EnsureValidSession
{
    public function __construct(private SessionManager $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $s = $this->sessions->current($request);
        $user = $request->user();
        $staffIdle = $user && $user->isStaff() && $s
            && $s->last_seen_at->lt(now()->subMinutes(config('platform.auth.staff_idle_minutes')));
        if (! $s || $staffIdle) {
            if ($s && $staffIdle) {
                $s->update(['revoked_at' => now(), 'revoke_reason' => 'idle']);
            }
            $this->sessions->end($request, 'invalid');
            if ($request->expectsJson()) {
                abort(401);
            }

            return redirect()->route('login')->with('status', __('ui.session_ended'));
        }
        if ($s->last_seen_at->lt(now()->subMinute())) {
            $s->update(['last_seen_at' => now()]);
        }
        $request->attributes->set('user_session', $s);

        return $next($request);
    }
}

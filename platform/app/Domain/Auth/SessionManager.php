<?php

namespace App\Domain\Auth;

use App\Models\User;
use App\Models\UserSession;
use App\Support\DbContext;
use App\Support\Ip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Server-side sessiyalar: hər girişdə yeni `user_sessions` sətri, eyni vaxtda cihaz limiti,
 * ləğvetmə (logout, parol bərpası, admin). Laravel sessiyası yalnız təsadüfi tokeni saxlayır.
 */
final class SessionManager
{
    public const KEY = 'auth_sid';

    public function start(User $user, Request $request): UserSession
    {
        Auth::login($user);
        DbContext::actAs($user->id);
        $request->session()->regenerate(true);   // session fixation-a qarşı rotation
        $raw = bin2hex(random_bytes(32));
        $request->session()->put(self::KEY, $raw);

        return DB::transaction(function () use ($user, $request, $raw) {
            // Eyni istifadəçinin paralel girişlərini seriyalaşdırır.
            DB::select('SELECT pg_advisory_xact_lock(?)', [crc32('sess:'.$user->id)]);
            $session = UserSession::create([
                'id' => hash('sha256', $raw), 'user_id' => $user->id,
                'ip_hash' => Ip::hash($request->ip()), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(), 'last_seen_at' => now(),
            ]);
            $this->enforceLimit($user);

            return $session;
        });
    }

    /** Limitdən artıq aktiv sessiya varsa, ən köhnələri ləğv edilir (default qayda, 1c-12). */
    public function enforceLimit(User $user): void
    {
        $limit = $this->limit();
        $ids = UserSession::where('user_id', $user->id)->whereNull('revoked_at')
            ->orderByDesc('created_at')->orderByDesc('last_seen_at')->pluck('id')->slice($limit);
        if ($ids->isNotEmpty()) {
            UserSession::whereIn('id', $ids)->update(['revoked_at' => now(), 'revoke_reason' => 'device_limit']);
        }
    }

    public function limit(): int
    {
        if (Schema::hasTable('billing_rules')) {
            $v = DB::table('billing_rules')->whereNull('course_id')->value('session_limit');
            if ($v) {
                return (int) $v;
            }
        }

        return (int) config('platform.auth.session_limit_default');
    }

    public function current(Request $request): ?UserSession
    {
        $raw = $request->session()->get(self::KEY);
        if (! is_string($raw) || ! Auth::check()) {
            return null;
        }
        $s = UserSession::find(hash('sha256', $raw));

        return ($s && $s->user_id === Auth::id() && $s->revoked_at === null) ? $s : null;
    }

    public function end(Request $request, string $reason = 'logout'): void
    {
        $raw = $request->session()->get(self::KEY);
        if (is_string($raw)) {
            UserSession::where('id', hash('sha256', $raw))->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'revoke_reason' => $reason]);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function revokeAll(User $user, string $reason): int
    {
        return UserSession::where('user_id', $user->id)->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoke_reason' => $reason]);
    }
}

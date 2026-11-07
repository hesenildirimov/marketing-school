<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\Audit;
use App\Domain\Auth\SessionManager;
use App\Domain\Auth\TokenService;
use App\Domain\Notifications\Notifier;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\DbContext;
use App\Support\Normalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class PasswordResetController extends Controller
{
    public function showForgot()
    {
        return view('auth.forgot');
    }

    /** Cavab həmişə eynidir (hesabın mövcudluğu açılmır). */
    public function send(Request $request, TokenService $tokens)
    {
        $data = $request->validate(['email' => 'required|string|max:254']);
        $user = User::where('email', Normalizer::emailLower($data['email']))->whereNull('deleted_at')
            ->whereNotNull('email_verified_at')->first();
        if ($user && $tokens->recentCount($user, TokenService::RESET) < config('platform.auth.resend_limit_per_hour')) {
            DB::transaction(function () use ($user, $tokens) {
                DbContext::actAs($user->id);
                $raw = $tokens->issue($user, TokenService::RESET, now()->addMinutes(config('platform.auth.password_reset_ttl_minutes')));
                Notifier::send($user, 'password_reset', ['url' => route('password.reset', ['t' => $raw])],
                    ['priority' => Notifier::P_SECURITY, 'channels' => ['email'], 'dedupe' => TokenService::hash($raw)]);
                DbContext::actAs(null);
            });
        }

        return back()->with('status', __('ui.reset_sent'));
    }

    public function showReset(Request $request, TokenService $tokens)
    {
        $t = (string) $request->query('t');

        return response()->view('auth.reset', ['token' => $t, 'valid' => $t !== '' && (bool) $tokens->peek($t, TokenService::RESET)])
            ->header('Referrer-Policy', 'no-referrer');
    }

    /** Uğurlu bərpadan sonra BÜTÜN sessiyalar ləğv olunur. */
    public function reset(Request $request, TokenService $tokens, SessionManager $sessions)
    {
        $data = $request->validate([
            't' => 'required|string|max:100',
            'password' => ['required', 'confirmed', Password::min(config('platform.auth.password_min_length'))->letters()->numbers()],
        ]);
        $ok = DB::transaction(function () use ($tokens, $data, $sessions) {
            $token = $tokens->consume($data['t'], TokenService::RESET);
            if (! $token) {
                return false;
            }
            $user = User::find($token->user_id);
            DbContext::actAs($user->id);
            $user->password = $data['password'];
            $user->save();
            $sessions->revokeAll($user, 'password_reset');
            Audit::record('auth.password_reset', $user, actorId: $user->id);

            return true;
        });
        if (! $ok) {
            return back()->withErrors(['t' => __('ui.link_invalid')]);
        }
        if (auth()->check()) {
            $sessions->end($request, 'password_reset');
        }

        return redirect()->route('login')->with('status', __('ui.password_changed'));
    }
}

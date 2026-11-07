<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\Audit;
use App\Domain\Auth\TwoFactor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function challenge(Request $request, TwoFactor $tf)
    {
        $user = $request->user();
        if (! $user->isStaff() || $request->attributes->get('user_session')->two_factor_at) {
            return redirect()->route('dashboard');
        }
        if (! $tf->isConfirmed($user)) {
            $secret = $tf->begin($user);

            return view('auth.2fa-setup', ['qr' => $tf->qrSvg($user, $secret), 'secret' => $secret->secret_encrypted]);
        }

        return view('auth.2fa-challenge');
    }

    public function verify(Request $request, TwoFactor $tf)
    {
        $data = $request->validate(['code' => 'nullable|string|max:20', 'recovery' => 'nullable|string|max:30']);
        $user = $request->user();
        $session = $request->attributes->get('user_session');
        $setup = ! $tf->isConfirmed($user);
        $ok = ! empty($data['code']) ? $tf->verify($user, $data['code'])
            : (! $setup && ! empty($data['recovery']) && $tf->useRecoveryCode($user, $data['recovery']));
        if (! $ok) {
            Audit::record('auth.2fa_failed', $user, actorId: $user->id);

            return back()->withErrors(['code' => __('ui.code_invalid')]);
        }
        $request->session()->regenerate(true);  // səlahiyyət yüksəlir: session rotation
        $session->update(['two_factor_at' => now(), 'reauth_at' => now()]);
        if ($setup) {
            $codes = $tf->confirm($user);
            Audit::record('auth.2fa_enabled', $user, actorId: $user->id);

            return view('auth.recovery-codes', ['codes' => $codes]);
        }
        Audit::record(! empty($data['recovery']) ? 'auth.2fa_recovery_used' : 'auth.2fa_passed', $user, actorId: $user->id);

        return redirect()->intended(route($user->isAdmin() ? 'admin.home' : 'dashboard'));
    }
}

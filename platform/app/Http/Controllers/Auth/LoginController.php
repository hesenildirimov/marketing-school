<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\Audit;
use App\Domain\Auth\SessionManager;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    // Mövcud olmayan hesab üçün də hash yoxlanır ki, cavab müddəti hesabın varlığını açmasın.
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=3,p=1$Zlducm9hY3cxWi9kM21NYQ$0rmly9Efxxpn3kVnDZaGOHotJXjwDPS6FBjIHPF809s';

    public function show()
    {
        return auth()->check() ? redirect()->route('dashboard') : view('auth.login');
    }

    public function login(Request $request, SessionManager $sessions)
    {
        $data = $request->validate(['email' => 'required|string|max:254', 'password' => 'required|string|max:200']);
        $user = User::where('email', Normalizer::emailLower($data['email']))->whereNull('deleted_at')->first();
        $ok = Hash::check($data['password'], $user?->password ?: self::DUMMY_HASH);
        if (! $user || ! $ok || ! $user->email_verified_at || ! $user->password) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __('ui.login_failed')]);
        }
        if (Hash::needsRehash($user->password)) {
            $user->password = $data['password'];
            $user->save();
        }
        $sessions->start($user, $request);
        Audit::record('auth.login', $user, actorId: $user->id);

        return $user->isStaff() ? redirect()->route('2fa.challenge') : redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request, SessionManager $sessions)
    {
        $sessions->end($request);

        return redirect()->route('login');
    }
}

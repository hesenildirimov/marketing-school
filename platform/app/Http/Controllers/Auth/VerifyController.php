<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\Audit;
use App\Domain\Auth\SessionManager;
use App\Domain\Auth\TokenService;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use App\Support\DbContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/**
 * E-mail təsdiqi + ilk parol. GET yalnız formanı göstərir (e-mail skanerləri tokeni yandırmasın),
 * token POST-da atomik istehlak olunur. Link daimi giriş vermir.
 */
class VerifyController extends Controller
{
    public function show(Request $request, TokenService $tokens)
    {
        $t = (string) $request->query('t');
        $valid = $t !== '' && $tokens->peek($t, TokenService::VERIFY);

        return response()->view('auth.verify', ['token' => $t, 'valid' => (bool) $valid])
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function complete(Request $request, TokenService $tokens, SessionManager $sessions)
    {
        $data = $request->validate([
            't' => 'required|string|max:100',
            'password' => ['required', 'confirmed', Password::min(config('platform.auth.password_min_length'))->letters()->numbers()],
        ]);
        $user = DB::transaction(function () use ($tokens, $data) {
            $token = $tokens->consume($data['t'], TokenService::VERIFY);
            if (! $token) {
                return null;
            }
            $user = User::lockForUpdate()->find($token->user_id);
            DbContext::actAs($user->id);
            $user->password = $data['password'];
            $user->email_verified_at ??= now();
            $user->save();
            event('platform.email_verified', [$user]);
            Audit::record('auth.email_verified', $user, actorId: $user->id);

            return $user;
        });
        if (! $user) {
            return redirect()->route('verify.show')->withErrors(['t' => __('ui.link_invalid')]);
        }
        $sessions->start($user, $request);
        if ($user->isStaff()) {
            return redirect()->route('2fa.challenge');
        }
        // Demo istifadəçisi birbaşa kursuna gedir; demo bitibsə "Kursu al" ekranını görür.
        $demoCourse = DB::table('demo_claims')->where('user_id', $user->id)->value('course_id');

        return $demoCourse ? redirect()->route('course.show', Course::find($demoCourse)) : redirect()->route('dashboard');
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Accounts\AccountRegistrar;
use App\Domain\Notifications\Notifier;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Demosuz birbaşa qeydiyyat (kursu dərhal almaq istəyənlər üçün). */
class RegisterController extends Controller
{
    public function show()
    {
        return view('auth.register');
    }

    public function store(Request $request, AccountRegistrar $registrar)
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email:rfc|max:254',
            'phone' => 'required|string|max:30',
            'consent' => 'accepted',
            'website' => 'nullable|size:0', // honeypot
        ]);
        $phone = Normalizer::phone($data['phone']);
        if (! $phone) {
            return back()->withInput()->withErrors(['phone' => __('ui.phone_invalid')]);
        }
        $email = Normalizer::emailLower($data['email']);
        try {
            DB::transaction(function () use ($registrar, $data, $email, $phone) {
                $user = $registrar->create($data['name'], $email, $phone, app()->getLocale());
                $registrar->sendVerifyLink($user);
            });
        } catch (UniqueConstraintViolationException) {
            // Hesab artıq var: cavab eynidir (enumeration yoxdur), sahibinə məlumat e-maili gedir.
            $existing = User::where('email', $email)->first();
            if ($existing) {
                Notifier::send($existing, 'account_exists', ['url' => route('login')], ['channels' => ['email'], 'dedupe' => now()->format('YmdH')]);
            }
        }

        return redirect()->route('check-email');
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ReauthController extends Controller
{
    public function show()
    {
        return view('auth.reauth');
    }

    public function confirm(Request $request)
    {
        $request->validate(['password' => 'required|string|max:200']);
        if (! Hash::check($request->input('password'), $request->user()->password)) {
            return back()->withErrors(['password' => __('ui.login_failed')]);
        }
        $request->attributes->get('user_session')->update(['reauth_at' => now()]);

        return redirect()->intended(route('dashboard'));
    }
}

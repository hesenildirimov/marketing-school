<?php

namespace App\Http\Controllers;

use App\Domain\Audit\Audit;
use App\Models\UserSession;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function sessions(Request $request)
    {
        $current = $request->attributes->get('user_session');
        $sessions = UserSession::where('user_id', $request->user()->id)->whereNull('revoked_at')->orderByDesc('last_seen_at')->get();

        return view('account.sessions', compact('sessions', 'current'));
    }

    public function revokeSession(Request $request, string $id)
    {
        $s = UserSession::where('user_id', $request->user()->id)->where('id', $id)->firstOrFail();
        $s->update(['revoked_at' => now(), 'revoke_reason' => 'user']);
        Audit::record('auth.session_revoked', $s->user, entityType: 'UserSession', reason: 'user');

        return back()->with('status', __('ui.saved'));
    }

    public function locale(Request $request)
    {
        $data = $request->validate(['locale' => 'required|in:'.implode(',', config('platform.locales'))]);
        $request->user()->forceFill(['locale' => $data['locale']])->save();

        return back();
    }
}

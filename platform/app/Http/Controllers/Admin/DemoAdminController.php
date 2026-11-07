<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Audit;
use App\Domain\Demo\DemoService;
use App\Http\Controllers\Controller;
use App\Models\DemoClaim;
use App\Models\User;
use Illuminate\Http\Request;

class DemoAdminController extends Controller
{
    public function index(Request $request)
    {
        $q = DemoClaim::with(['user', 'course'])->orderByDesc('started_at');
        if ($s = trim((string) $request->query('q'))) {
            $q->whereHas('user', fn ($u) => $u->where('email', 'ilike', '%'.str_replace(['%', '_'], ['\%', '\_'], $s).'%'));
        }

        return view('admin.demos.index', ['claims' => $q->paginate(50)->withQueryString()]);
    }

    /** Admin e-mail linkini yenidən göndərir (rate limit-siz, audit log ilə). Demo müddəti dəyişmir. */
    public function resend(Request $request, User $user, DemoService $demo)
    {
        $ok = $demo->resend($user, byAdmin: true);
        Audit::record('demo.link_resent_by_admin', $user, after: ['sent' => $ok]);

        return back()->with('status', $ok ? __('ui.saved') : __('admin.already_verified'));
    }
}

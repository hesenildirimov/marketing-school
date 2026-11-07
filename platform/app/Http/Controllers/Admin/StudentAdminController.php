<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\AccessPolicy;
use App\Domain\Audit\Audit;
use App\Domain\Auth\SessionManager;
use App\Domain\Content\Manifest;
use App\Domain\Learning\ProgressService;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;

class StudentAdminController extends Controller
{
    public function index(Request $request)
    {
        $q = User::whereNull('deleted_at')->orderByDesc('id');
        if ($s = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($s)).'%';
            $q->where(fn ($w) => $w->where('email', 'like', $like)->orWhereRaw('lower(name) like ?', [$like])->orWhere('phone_e164', 'like', $like));
        }

        return view('admin.students.index', ['users' => $q->paginate(50)->withQueryString()]);
    }

    public function show(User $user, AccessPolicy $access, ProgressService $progress)
    {
        $rows = Enrollment::with('course')->where('user_id', $user->id)->get()->map(fn ($e) => [
            'e' => $e, 'status' => $access->status($e), 'grace_until' => $access->graceUntil($e),
            'progress' => $progress->courseProgress($user, Manifest::for($e->course_version_id)),
            'sub' => Subscription::where('enrollment_id', $e->id)->where('status', '<>', 'canceled')->first(),
        ]);

        return view('admin.students.show', ['u' => $user, 'rows' => $rows, 'demo' => $access->demoClaim($user),
            'sessions' => UserSession::where('user_id', $user->id)->whereNull('revoked_at')->get()]);
    }

    public function revokeSessions(Request $request, User $user, SessionManager $sessions)
    {
        $n = $sessions->revokeAll($user, 'admin');
        Audit::record('auth.sessions_revoked_by_admin', $user, after: ['count' => $n]);

        return back()->with('status', __('ui.saved'));
    }

    /** Admin tərəfindən ləğv (məcburi səbəb, audit). Akademik məlumat silinmir. */
    public function cancelEnrollment(Request $request, Enrollment $enrollment)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);
        $enrollment->update(['canceled_at' => now(), 'cancel_reason' => mb_substr('admin: '.$data['reason'], 0, 200)]);
        Audit::record('billing.enrollment_canceled', $enrollment, reason: $data['reason']);

        return back()->with('status', __('ui.saved'));
    }
}

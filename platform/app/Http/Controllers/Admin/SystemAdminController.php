<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Analytics\AnalyticsService;
use App\Domain\Audit\Audit;
use App\Domain\Notifications\NotificationSender;
use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Course;
use Illuminate\Http\Request;

class SystemAdminController extends Controller
{
    public function notifications(Request $request)
    {
        $status = in_array($request->query('status'), ['pending', 'sent', 'failed', 'canceled'], true) ? $request->query('status') : 'failed';
        $items = AppNotification::with('user')->where('channel', 'email')->where('status', $status)->orderByDesc('updated_at')->paginate(50)->withQueryString();
        $counts = AppNotification::where('channel', 'email')->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('admin.notifications', compact('items', 'status', 'counts'));
    }

    public function resend(Request $request, AppNotification $notification)
    {
        abort_unless($notification->channel === 'email', 422);
        NotificationSender::requeue($notification);
        Audit::record('notification.resent_by_admin', $notification, after: ['event' => $notification->event_type]);

        return back()->with('status', __('ui.saved'));
    }

    public function audit(Request $request)
    {
        $q = AuditLog::orderByDesc('id');
        if ($a = $request->query('action')) {
            $q->where('action', 'like', str_replace(['%', '_'], ['\%', '\_'], $a).'%');
        }
        if ($t = $request->query('entity')) {
            $q->where('entity_type', $t);
        }

        return view('admin.audit', ['logs' => $q->paginate(100)->withQueryString(), 'chainBroken' => Audit::verifyChain()]);
    }

    public function analytics(Request $request, AnalyticsService $a)
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90, 365], true) ? (int) $request->query('days') : 30;

        return view('admin.analytics', ['days' => $days, 'data' => Course::orderBy('id')->get()->map(fn ($c) => ['course' => $c, 'm' => $a->forCourse($c, $days)])]);
    }
}

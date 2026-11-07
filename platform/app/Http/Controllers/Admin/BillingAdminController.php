<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Audit;
use App\Domain\Billing\BillingService;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\Controller;
use App\Models\BillingRule;
use App\Models\Course;
use App\Models\PaymentOrder;
use App\Models\PaymentWebhookEvent;
use App\Models\Price;
use App\Models\User;
use App\Support\Normalizer;
use App\Support\Tz;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Yalnız admin. Bütün dəyişikliklər audit log-a yazılır; həssas əməliyyatlar təkrar parol tələb edir. */
class BillingAdminController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.billing.index', [
            'orders' => PaymentOrder::with('user', 'course')->orderByDesc('created_at')->limit(50)->get(),
            'events' => PaymentWebhookEvent::whereIn('status', ['failed', 'received'])->orderByDesc('received_at')->limit(50)->get(),
            'rules' => BillingRule::whereNull('course_id')->first(),
            'prices' => Price::orderByDesc('valid_from')->get()->groupBy('course_id'),
            'courses' => Course::orderBy('id')->get(),
        ]);
    }

    public function retryWebhook(Request $request, PaymentWebhookEvent $event, BillingService $billing)
    {
        BillingController::process($event, (string) ($event->payload_redacted['order_id'] ?? ''), $billing);
        Audit::record('billing.webhook_retried', $event, actorId: $request->user()->id);

        return back()->with('status', $event->fresh()->status);
    }

    public function refund(Request $request, PaymentOrder $order, BillingService $billing)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);

        return back()->with('status', $billing->refund($order, $request->user(), $data['reason']));
    }

    public function activate(Request $request, BillingService $billing)
    {
        $data = $request->validate(['email' => 'required|string', 'course_id' => 'required|integer', 'months' => 'required|integer|min:1|max:24',
            'reason' => 'required|string|min:5|max:1000']);
        $student = User::where('email', Normalizer::emailLower($data['email']))->first();
        if (! $student) {
            return back()->withErrors(['email' => __('billing.student_not_found')]);
        }
        $e = $billing->manualActivate($request->user(), $student, Course::findOrFail($data['course_id']), (int) $data['months'], $data['reason']);

        return back()->with('status', __('billing.activated', ['date' => Tz::fmt($e->paid_until, 'd.m.Y')]));
    }

    /** grace_period_days və digər qaydalar — hər dəyişiklik audit log-a yazılır (bölmə 1b). */
    public function rules(Request $request)
    {
        $data = $request->validate([
            'grace_period_days' => 'required|integer|min:0|max:30', 'min_commitment_months' => 'nullable|integer|min:1|max:12',
            'session_limit' => 'required|integer|min:1|max:10', 'mentoring_cancel_hours' => 'required|integer|min:0|max:168',
            'upload_file_mb' => 'required|integer|min:1|max:100', 'upload_quota_mb' => 'required|integer|min:10|max:5000',
            'upload_files_per_submission' => 'required|integer|min:1|max:20', 'reason' => 'required|string|min:5|max:1000',
        ]);
        $r = BillingRule::whereNull('course_id')->firstOrFail();
        $before = $r->only(array_keys($data));
        $r->fill(collect($data)->except('reason')->all() + ['auto_stop_on_completion' => $request->boolean('auto_stop_on_completion'), 'updated_by' => $request->user()->id])->save();
        Audit::record('billing.rules_changed', $r, before: $before, after: $r->only(array_keys($data)), reason: $data['reason']);

        return back()->with('status', __('ui.saved'));
    }

    /** Yeni qiymət gələcək tarixdən qüvvəyə minir; köhnə sifarişlər dəyişmir. */
    public function price(Request $request)
    {
        $data = $request->validate(['course_id' => 'required|integer', 'amount' => 'required|numeric|min:1|max:10000', 'valid_from' => 'required|date|after_or_equal:today']);
        $p = Price::create(['course_id' => $data['course_id'], 'amount_qepik' => (int) round($data['amount'] * 100), 'currency' => 'AZN',
            'valid_from' => Carbon::parse($data['valid_from'], 'Asia/Baku')->startOfDay(), 'created_by' => $request->user()->id, 'created_at' => now()]);
        Audit::record('billing.price_added', $p, after: ['amount_qepik' => $p->amount_qepik, 'valid_from' => $p->valid_from->toIso8601String()]);

        return back()->with('status', __('ui.saved'));
    }
}

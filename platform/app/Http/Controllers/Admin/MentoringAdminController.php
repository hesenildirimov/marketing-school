<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\AccessPolicy;
use App\Domain\Audit\Audit;
use App\Domain\Mentoring\MentoringService;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\MentorBooking;
use App\Models\MentorSlot;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class MentoringAdminController extends Controller
{
    public function __construct(private AccessPolicy $access) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $courses = Course::all()->filter(fn ($c) => $this->access->isStaffFor($user, $c));
        $q = MentorSlot::with('instructor')->whereIn('course_id', $courses->pluck('id'))->whereNull('deleted_at')->where('ends_at', '>', now()->subDays(7));
        if (! $user->isAdmin()) {
            $q->where('instructor_id', $user->id);
        }
        $slots = $q->orderBy('starts_at')->get();
        $bookings = MentorBooking::with('user')->whereIn('slot_id', $slots->pluck('id'))->whereIn('status', ['booked', 'completed', 'no_show'])->get()->keyBy('slot_id');

        return view('admin.mentoring.index', compact('courses', 'slots', 'bookings'));
    }

    /** Təlimçi boş vaxtlarını təqvimə əlavə edir (Bakı vaxtı). Üst-üstə düşən slot DB tərəfindən rədd olunur. */
    public function store(Request $request)
    {
        $data = $request->validate(['course_id' => 'required|integer', 'date' => 'required|date_format:Y-m-d', 'time' => 'required|date_format:H:i',
            'duration' => 'required|integer|min:15|max:180', 'count' => 'nullable|integer|min:1|max:12', 'meeting_url' => 'nullable|url:https|max:500']);
        $course = Course::findOrFail($data['course_id']);
        abort_unless($this->access->isStaffFor($request->user(), $course), 403);
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i', $data['date'].' '.$data['time'], 'Asia/Baku');
        abort_if($start->isPast(), 422);
        $made = 0;
        for ($i = 0; $i < ($data['count'] ?? 1); $i++) {
            $s = $start->addMinutes($i * $data['duration']);
            try {
                MentorSlot::create(['course_id' => $course->id, 'instructor_id' => $request->user()->id, 'starts_at' => $s->utc(),
                    'ends_at' => $s->addMinutes((int) $data['duration'])->utc(), 'meeting_url_encrypted' => $data['meeting_url'] ?? null]);
                $made++;
            } catch (QueryException) {
                // üst-üstə düşən slot (EXCLUDE constraint) — ötürülür
            }
        }

        return back()->with('status', __('mentoring.slots_created', ['n' => $made]));
    }

    public function destroy(Request $request, MentorSlot $slot)
    {
        abort_unless($request->user()->isAdmin() || $slot->instructor_id === $request->user()->id, 403);
        abort_if(MentorBooking::where('slot_id', $slot->id)->where('status', 'booked')->exists(), 422, __('mentoring.slot_booked'));
        $slot->update(['deleted_at' => now()]);

        return back();
    }

    public function mark(Request $request, MentorBooking $booking, MentoringService $svc)
    {
        abort_unless($this->access->isStaffFor($request->user(), Course::find($booking->course_id)), 403);
        $data = $request->validate(['status' => 'required|in:completed,no_show,cancel']);
        if ($data['status'] === 'cancel') {
            $svc->cancel($booking, $request->user(), admin: true);
        } else {
            $booking->update(['status' => $data['status']]);
            Audit::record('mentoring.'.$data['status'], $booking);
        }

        return back();
    }
}

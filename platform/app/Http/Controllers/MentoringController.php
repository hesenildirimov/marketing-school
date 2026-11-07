<?php

namespace App\Http\Controllers;

use App\Domain\Access\AccessPolicy;
use App\Domain\Mentoring\MentoringService;
use App\Models\Course;
use App\Models\MentorBooking;
use App\Models\MentorSlot;
use Illuminate\Http\Request;

/** 1:1 mentorluq: kursu almayanlar və demo istifadəçilər bu bölməni görmür (403); açılış bütün dərslər tamamlananda. */
class MentoringController extends Controller
{
    public function __construct(private AccessPolicy $access, private MentoringService $svc) {}

    public function index(Request $request, Course $course)
    {
        $user = $request->user();
        abort_unless($this->access->canSeePaidFeatures($user, $course), 403);
        $unlocked = $this->access->canUseMentoring($user, $course);
        $booking = MentorBooking::with('slot')->where('user_id', $user->id)->where('course_id', $course->id)->whereIn('status', ['booked', 'completed', 'no_show'])->first();
        $slots = $unlocked ? MentorSlot::where('course_id', $course->id)->whereNull('deleted_at')->where('starts_at', '>', now()->addHour())
            ->where('is_booked', false)
            ->orderBy('starts_at')->limit(60)->get() : collect();

        return view('mentoring.index', compact('course', 'unlocked', 'booking', 'slots'));
    }

    public function book(Request $request, Course $course)
    {
        $data = $request->validate(['slot_id' => 'required|integer']);
        $this->svc->book($request->user(), $course, (int) $data['slot_id']);

        return redirect()->route('mentoring.index', $course)->with('status', __('mentoring.booked'));
    }

    public function cancel(Request $request, MentorBooking $booking)
    {
        $this->svc->cancel($booking, $request->user());

        return back()->with('status', __('mentoring.canceled'));
    }

    public function reschedule(Request $request, MentorBooking $booking)
    {
        $data = $request->validate(['slot_id' => 'required|integer']);
        abort_unless($booking->user_id === $request->user()->id, 403);
        $this->svc->reschedule($booking, $request->user(), (int) $data['slot_id']);

        return back()->with('status', __('mentoring.booked'));
    }
}

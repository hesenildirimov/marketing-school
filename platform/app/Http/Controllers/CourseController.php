<?php

namespace App\Http\Controllers;

use App\Domain\Access\AccessPolicy;
use App\Domain\Learning\ProgressService;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\PersonalNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    public function __construct(private AccessPolicy $access, private ProgressService $progress) {}

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $cards = [];
        foreach (Course::orderBy('id')->get() as $course) {
            $e = $this->access->enrollment($user, $course);
            $status = $this->access->status($e);
            $demo = $this->access->activeDemo($user, $course);
            $staff = $this->access->isStaffFor($user, $course);
            $claim = $this->access->demoClaim($user);
            if (! $e && ! $demo && ! $staff && ! ($claim && (int) $claim->course_id === $course->id)) {
                $cards[] = ['course' => $course, 'state' => 'available'];

                continue;
            }
            $m = $this->access->manifestFor($user, $course);
            $cards[] = [
                'course' => $course, 'enrollment' => $e, 'status' => $status, 'demo' => $demo, 'staff' => $staff,
                'state' => $staff ? 'staff' : (in_array($status, ['active', 'past_due']) ? $status : ($demo ? 'demo' : ($e ? $status : 'demo_expired'))),
                'progress' => $m ? $this->progress->courseProgress($user, $m) : null,
                'grace_until' => $e ? $this->access->graceUntil($e) : null,
            ];
        }

        return view('dashboard', ['cards' => $cards, 'user' => $user]);
    }

    public function show(Request $request, Course $course)
    {
        $user = $request->user();
        if (! $this->access->canEnterCourse($user, $course)) {
            return $this->buyScreen($course, $user);
        }
        $m = $this->access->manifestFor($user, $course);
        $accessible = $m->lessons->filter(fn ($l) => $this->access->canViewLesson($user, $course, $l->id));
        $last = DB::table('lesson_progress')->where('user_id', $user->id)->where('course_id', $course->id)
            ->whereIn('lesson_id', $accessible->pluck('id')->all() ?: [0])->orderByDesc('updated_at')->value('lesson_id');
        $target = $last ?? $accessible->first()?->id;

        return $target ? redirect()->route('lesson.show', [$course, $target]) : view('course.empty', compact('course'));
    }

    public function lesson(Request $request, Course $course, Lesson $lesson)
    {
        $user = $request->user();
        abort_unless($lesson->course_id === $course->id, 404);
        if (! $this->access->canEnterCourse($user, $course)) {
            return $this->buyScreen($course, $user);
        }
        $m = $this->access->manifestFor($user, $course);
        abort_unless($m->hasLesson($lesson->id) || $this->access->isStaffFor($user, $course), 404);
        $canView = $this->access->canViewLesson($user, $course, $lesson->id);
        $p = $this->progress->courseProgress($user, $m);
        $paid = $this->access->canSeePaidFeatures($user, $course);
        $demo = $this->access->activeDemo($user, $course);

        return response()->view('course.lesson', [
            'course' => $course, 'lesson' => $lesson, 'manifest' => $m, 'progress' => $p, 'canView' => $canView,
            // Kilidli dərsin məzmunu (mətn, material, video) serverdə heç göndərilmir
            'revision' => $canView ? $lesson->revision : null,
            'notes' => $canView ? PersonalNote::where('user_id', $user->id)->where('lesson_id', $lesson->id)->orderBy('at_second')->orderBy('id')->get() : collect(),
            'paid' => $paid, 'demo' => $demo, 'next' => $m->next($lesson->id),
            'locked' => fn ($l) => ! $this->access->canViewLesson($user, $course, $l->id),
            'moduleDone' => fn ($mid) => $this->progress->moduleCompleted($m, $mid, $p['rows']),
            'mentoring' => $this->access->canUseMentoring($user, $course),
            'extraTabs' => app()->bound('lesson.tabs') ? app('lesson.tabs') : [],
        ]);
    }

    private function buyScreen(Course $course, $user)
    {
        $claim = $this->access->demoClaim($user);
        $e = Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->first();

        return response()->view('course.buy', [
            'course' => $course, 'enrollment' => $e, 'status' => $this->access->status($e),
            'demoExpired' => $claim && (int) $claim->course_id === $course->id,
        ], 403);
    }
}

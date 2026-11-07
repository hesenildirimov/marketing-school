<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\AccessPolicy;
use App\Domain\Assessment\AssignmentService;
use App\Domain\Audit\Audit;
use App\Domain\Live\LiveService;
use App\Domain\Video\VideoProvider;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LiveQuestion;
use App\Models\LiveSession;
use App\Models\VideoAsset;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class LiveAdminController extends Controller
{
    public function __construct(private AccessPolicy $access, private LiveService $live) {}

    private function auth(Request $r, Course $c): void
    {
        abort_unless($this->access->isStaffFor($r->user(), $c), 403);
    }

    public function index(Request $request)
    {
        $courses = Course::all()->filter(fn ($c) => $this->access->isStaffFor($request->user(), $c));
        $sessions = LiveSession::whereIn('course_id', $courses->pluck('id'))->where('starts_at', '>', now()->subMonths(2))->orderBy('starts_at')->get()->groupBy('course_id');

        return view('admin.live.index', compact('courses', 'sessions'));
    }

    public function generate(Request $request, Course $course)
    {
        $this->auth($request, $course);
        $n = $this->live->generate($course);

        return back()->with('status', __('live.generated', ['n' => $n]));
    }

    public function edit(Request $request, LiveSession $session)
    {
        $this->auth($request, $session->course);
        $assignments = app(AssignmentService::class)->forVersion((int) $session->course->current_version_id);

        return view('admin.live.edit', ['s' => $session, 'questions' => LiveQuestion::where('live_session_id', $session->id)->orderBy('id')->get(),
            'assignments' => $assignments, 'selected' => \DB::table('live_session_assignments')->where('live_session_id', $session->id)->pluck('assignment_id')->all()]);
    }

    /** Tarix/saat Asia/Baku-da daxil edilir, UTC saxlanılır; dəyişiklikdə bildiriş + .ics SEQUENCE++. */
    public function update(Request $request, LiveSession $session)
    {
        $this->auth($request, $session->course);
        $data = $request->validate(['date' => 'required|date_format:Y-m-d', 'time' => 'required|date_format:H:i',
            'duration' => 'required|integer|min:15|max:240', 'meeting_url' => 'nullable|url:https|max:500', 'assignments' => 'nullable|array', 'assignments.*' => 'integer']);
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i', $data['date'].' '.$data['time'], 'Asia/Baku');
        $this->live->update($session, $start, (int) $data['duration'], $request->has('meeting_url') ? (string) $data['meeting_url'] : null, $request->user());
        \DB::table('live_session_assignments')->where('live_session_id', $session->id)->delete();
        foreach ($data['assignments'] ?? [] as $aid) {
            \DB::table('live_session_assignments')->insertOrIgnore(['live_session_id' => $session->id, 'assignment_id' => $aid]);
        }

        return back()->with('status', __('ui.saved'));
    }

    public function cancel(Request $request, LiveSession $session)
    {
        $this->auth($request, $session->course);
        $this->live->cancel($session, $request->user(), $request->input('reason'));

        return back()->with('status', __('live.canceled'));
    }

    public function recordingTicket(Request $request, LiveSession $session, VideoProvider $video)
    {
        $this->auth($request, $session->course);
        $t = $video->createUpload('live '.$session->starts_at->toDateString());
        $asset = VideoAsset::create(['provider' => $video->name(), 'provider_video_id' => $t->providerVideoId, 'title' => 'live', 'status' => 'uploading']);
        $session->update(['recording_video_asset_id' => $asset->id]);
        Audit::record('live.recording_attached', $session, after: ['asset' => $asset->id]);

        return response()->json(['endpoint' => $t->endpoint, 'headers' => $t->headers]);
    }
}

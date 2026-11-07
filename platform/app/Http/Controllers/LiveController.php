<?php

namespace App\Http\Controllers;

use App\Domain\Access\AccessPolicy;
use App\Domain\Live\LiveService;
use App\Domain\Video\PlaybackService;
use App\Models\Course;
use App\Models\LiveQuestion;
use App\Models\LiveSession;
use App\Models\VideoAsset;
use Illuminate\Http\Request;

/** Canlı görüşlər: yalnız ödənişli tələbələr (active/past_due) və staff. Demo və kursu almayanlar üçün 403. */
class LiveController extends Controller
{
    public function __construct(private AccessPolicy $access) {}

    private function guard(Request $r, Course $c): void
    {
        abort_unless($this->access->canSeePaidFeatures($r->user(), $c), 403);
    }

    public function index(Request $request, Course $course)
    {
        $this->guard($request, $course);
        $upcoming = LiveSession::where('course_id', $course->id)->where('starts_at', '>', now()->subHours(2))->orderBy('starts_at')->limit(8)->get();
        $recordings = LiveSession::where('course_id', $course->id)->whereNotNull('recording_video_asset_id')->where('status', 'scheduled')
            ->orderByDesc('starts_at')->get();
        $next = $upcoming->firstWhere('status', 'scheduled');
        $mine = $next ? LiveQuestion::where('live_session_id', $next->id)->where('user_id', $request->user()->id)->orderBy('id')->get() : collect();
        $discuss = $next ? \DB::table('live_session_assignments as l')->join('assignments as a', 'a.id', '=', 'l.assignment_id')
            ->join('assignment_revisions as r', 'r.id', '=', 'a.current_revision_id')->where('l.live_session_id', $next->id)->pluck('r.title') : collect();

        return view('live.index', compact('course', 'upcoming', 'recordings', 'next', 'mine', 'discuss'));
    }

    public function ics(Request $request, LiveSession $session, LiveService $live)
    {
        $this->guard($request, $session->course);

        return response($live->ics($session, $session->status === 'canceled' ? 'CANCEL' : 'PUBLISH', app()->getLocale()), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'attachment; filename="marketing-school-'.$session->id.'.ics"',
        ]);
    }

    public function ask(Request $request, LiveSession $session)
    {
        $this->guard($request, $session->course);
        abort_unless($session->status === 'scheduled' && $session->starts_at->isFuture(), 422);
        $data = $request->validate(['body' => 'required|string|min:3|max:2000']);
        LiveQuestion::create(['live_session_id' => $session->id, 'user_id' => $request->user()->id, 'course_id' => $session->course_id,
            'body' => strip_tags($data['body']), 'created_at' => now()]);

        return back()->with('status', __('live.question_sent'));
    }

    public function recording(Request $request, LiveSession $session)
    {
        $this->guard($request, $session->course);
        abort_unless($session->recording_video_asset_id, 404);

        return view('live.recording', ['s' => $session, 'course' => $session->course]);
    }

    public function playback(Request $request, LiveSession $session, PlaybackService $p)
    {
        $grant = $p->grantRecording($request->user(), $session->course, VideoAsset::find($session->recording_video_asset_id),
            $request->input('kind') === 'refresh' ? 'refresh' : 'issue');
        if (! $grant) {
            return response()->json(['error' => 'access_denied'], 403);
        }

        return response()->json($grant + ['resume_at' => 0, 'watermark' => ['email' => $request->user()->email, 'code' => $request->user()->short_code]]);
    }
}

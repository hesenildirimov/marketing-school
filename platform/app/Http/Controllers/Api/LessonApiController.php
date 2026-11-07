<?php

namespace App\Http\Controllers\Api;

use App\Domain\Access\AccessPolicy;
use App\Domain\Learning\ProgressService;
use App\Domain\Video\PlaybackService;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\PersonalNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LessonApiController extends Controller
{
    public function __construct(private AccessPolicy $access) {}

    private function guard(Request $request, Lesson $lesson): void
    {
        abort_unless($this->access->canViewLesson($request->user(), $lesson->course, $lesson->id), 403, 'access_denied');
    }

    public function playback(Request $request, Lesson $lesson, PlaybackService $playback)
    {
        $kind = $request->input('kind') === 'refresh' ? 'refresh' : 'issue';
        $grant = $playback->grant($request->user(), $lesson->course, $lesson, $kind);
        if (! $grant) {
            return response()->json(['error' => 'access_denied', 'buy_url' => route('course.show', $lesson->course)], 403);
        }
        $user = $request->user();
        $pos = (int) DB::table('lesson_progress')->where(['user_id' => $user->id, 'lesson_id' => $lesson->id])->value('position_seconds');

        return response()->json($grant + [
            'resume_at' => $pos,
            // Watermark: e-mail + qısa kod (telefon göstərilmir)
            'watermark' => ['email' => $user->email, 'code' => $user->short_code],
        ]);
    }

    /** Heartbeat və sendBeacon (text/plain JSON) — eyni idempotent upsert. */
    public function progress(Request $request, Lesson $lesson, ProgressService $progress)
    {
        $this->guard($request, $lesson);
        $data = $request->isJson() ? $request->json()->all() : (json_decode($request->getContent(), true) ?: $request->all());
        $pos = filter_var($data['position'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 86400]]);
        abort_if($pos === false, 422);
        $dur = filter_var($data['duration'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 86400]]) ?: null;
        $row = $progress->heartbeat($request->user(), $lesson->course, $lesson->id, $pos, $dur);

        return response()->json(['position' => $row['position_seconds'], 'max_watched' => $row['max_watched_seconds'], 'percent' => (float) $row['watch_percent']]);
    }

    public function complete(Request $request, Lesson $lesson, ProgressService $progress)
    {
        $this->guard($request, $lesson);
        $row = $progress->complete($request->user(), $lesson->course, $lesson->id);
        if (! $request->expectsJson()) {
            return back()->with('status', __('course.completed_flash'));
        }

        return response()->json(['completed_at' => $row['completed_at']]);
    }

    public function notes(Request $request, Lesson $lesson)
    {
        $this->guard($request, $lesson);

        return PersonalNote::where('user_id', $request->user()->id)->where('lesson_id', $lesson->id)
            ->orderBy('at_second')->orderBy('id')->get(['id', 'at_second', 'body', 'created_at']);
    }

    public function storeNote(Request $request, Lesson $lesson)
    {
        $this->guard($request, $lesson);
        $data = $request->validate(['body' => 'required|string|max:5000', 'at_second' => 'nullable|integer|min:0|max:86400']);
        $note = PersonalNote::create(['user_id' => $request->user()->id, 'lesson_id' => $lesson->id, 'course_id' => $lesson->course_id,
            'at_second' => $data['at_second'] ?? null, 'body' => $data['body']]);

        return $request->expectsJson() ? response()->json($note->only(['id', 'at_second', 'body']), 201) : back();
    }

    public function deleteNote(Request $request, Lesson $lesson, int $note)
    {
        $this->guard($request, $lesson);
        // Sahiblik: başqasının qeydi tapılmır (həm WHERE, həm RLS)
        $n = PersonalNote::where('user_id', $request->user()->id)->where('lesson_id', $lesson->id)->findOrFail($note);
        $n->delete();

        return $request->expectsJson() ? response()->noContent() : back();
    }

    /** Player metrikləri: şəxsi məlumat yazılmır. */
    public function metrics(Request $request, Lesson $lesson)
    {
        $this->guard($request, $lesson);
        $d = json_decode($request->getContent(), true) ?: [];
        $int = fn ($k, $max) => isset($d[$k]) && is_numeric($d[$k]) ? max(0, min((int) $d[$k], $max)) : null;
        DB::table('player_metrics')->insert([
            'lesson_id' => $lesson->id, 'ttff_ms' => $int('ttff_ms', 120000), 'rebuffer_count' => $int('rebuffer_count', 1000) ?? 0,
            'rebuffer_ms' => $int('rebuffer_ms', 3600000) ?? 0, 'avg_bitrate_kbps' => $int('avg_bitrate_kbps', 50000),
            'quality_switches' => $int('quality_switches', 1000) ?? 0,
            'device' => in_array($d['device'] ?? null, ['mobile', 'desktop'], true) ? $d['device'] : null, 'created_at' => now(),
        ]);

        return response()->noContent();
    }
}

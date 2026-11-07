<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\AccessPolicy;
use App\Domain\Audit\Audit;
use App\Domain\Content\Manifest;
use App\Domain\Content\VersionService;
use App\Domain\Video\VideoProvider;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseVersion;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\VideoAsset;
use Illuminate\Http\Request;

/** Kurs, modul, dərs idarəsi. Təlimçi yalnız öz kursunu görür; versiyanı yalnız admin dərc edir. */
class CourseAdminController extends Controller
{
    public function __construct(private AccessPolicy $access, private VersionService $versions) {}

    private function authorizeCourse(Request $r, Course $c): void
    {
        abort_unless($this->access->isStaffFor($r->user(), $c), 403);
    }

    private static function tri(Request $r, string $field, bool $required = false): ?array
    {
        $v = array_filter(['az' => trim((string) $r->input("{$field}_az")), 'en' => trim((string) $r->input("{$field}_en")), 'ru' => trim((string) $r->input("{$field}_ru"))], 'strlen');
        abort_if($required && empty($v['az']), 422, 'AZ field is required');

        return $v ?: null;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $courses = Course::orderBy('id')->get()->filter(fn ($c) => $this->access->isStaffFor($user, $c));

        return view('admin.courses.index', compact('courses'));
    }

    public function show(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);
        $draft = $course->draft();

        return view('admin.courses.show', [
            'course' => $course, 'draft' => $draft,
            'current' => $course->current_version_id ? Manifest::for($course->current_version_id) : null,
            'draftManifest' => $draft ? Manifest::for($draft->id) : null,
            'impact' => $draft ? $this->versions->impact($draft) : null,
            'versions' => $course->versions()->orderByDesc('version_no')->get(),
        ]);
    }

    public function createDraft(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);
        $this->versions->draft($course);

        return back()->with('status', __('admin.draft_created'));
    }

    private function draftOf(Course $course): CourseVersion
    {
        return $course->draft() ?? abort(422, 'No draft');
    }

    public function storeModule(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);
        $request->validate(['title_az' => 'required|string|max:200']);
        $m = $this->versions->addModule($this->draftOf($course), self::tri($request, 'title', true), self::tri($request, 'description'));
        Audit::record('content.module_added', $m, after: ['title' => $m->title]);

        return back();
    }

    public function updateModule(Request $request, Course $course, Module $module)
    {
        $this->authorizeCourse($request, $course);
        abort_unless($module->course_id === $course->id, 404);
        $request->validate(['title_az' => 'required|string|max:200']);
        $before = $module->only(['title', 'description']);
        $module->update(['title' => self::tri($request, 'title', true), 'description' => self::tri($request, 'description')]);
        Audit::record('content.module_updated', $module, before: $before, after: $module->only(['title', 'description']));
        Manifest::forget();

        return back()->with('status', __('ui.saved'));
    }

    public function moduleAction(Request $request, Course $course, Module $module)
    {
        $this->authorizeCourse($request, $course);
        abort_unless($module->course_id === $course->id, 404);
        $draft = $this->draftOf($course);
        match ($request->input('action')) {
            'up' => $this->versions->moveModule($draft, $module->id, -1),
            'down' => $this->versions->moveModule($draft, $module->id, 1),
            'demo_on' => $this->versions->setDemoOpen($draft, $module->id, true),
            'demo_off' => $this->versions->setDemoOpen($draft, $module->id, false),
            default => abort(422),
        };

        return back();
    }

    public function storeLesson(Request $request, Course $course, Module $module)
    {
        $this->authorizeCourse($request, $course);
        abort_unless($module->course_id === $course->id, 404);
        $request->validate(['title_az' => 'required|string|max:200']);
        $l = $this->versions->addLesson($this->draftOf($course), $module, self::tri($request, 'title', true), self::tri($request, 'body'), $request->user());
        Audit::record('content.lesson_added', $l, after: ['module_id' => $module->id]);

        return redirect()->route('admin.lessons.edit', [$course, $l]);
    }

    public function lessonAction(Request $request, Course $course, Lesson $lesson)
    {
        $this->authorizeCourse($request, $course);
        abort_unless($lesson->course_id === $course->id, 404);
        $draft = $this->draftOf($course);
        match ($request->input('action')) {
            'up' => $this->versions->moveLesson($draft, $lesson->id, -1),
            'down' => $this->versions->moveLesson($draft, $lesson->id, 1),
            'remove' => $this->versions->removeLesson($draft, $lesson->id),
            default => abort(422),
        };

        return back();
    }

    public function editLesson(Request $request, Course $course, Lesson $lesson)
    {
        $this->authorizeCourse($request, $course);
        abort_unless($lesson->course_id === $course->id, 404);

        return view('admin.lessons.edit', ['course' => $course, 'lesson' => $lesson, 'rev' => $lesson->revision,
            'video' => $lesson->revision?->video, 'revisions' => $lesson->revisions()->get()]);
    }

    /** Dərsin məzmunu dəyişəndə yeni revizya yaranır, köhnələr saxlanılır. */
    public function updateLesson(Request $request, Course $course, Lesson $lesson)
    {
        $this->authorizeCourse($request, $course);
        abort_unless($lesson->course_id === $course->id, 404);
        $request->validate(['title_az' => 'required|string|max:200', 'chapters' => 'nullable|string|max:5000',
            'materials' => 'nullable|string|max:5000', 'change_note' => 'nullable|string|max:500']);
        $this->versions->revise($lesson, [
            'title' => self::tri($request, 'title', true), 'body' => self::tri($request, 'body'),
            'chapters' => self::parseChapters((string) $request->input('chapters')),
            'materials' => self::parseMaterials((string) $request->input('materials')),
        ], $request->user(), $request->input('change_note'));

        return back()->with('status', __('ui.saved'));
    }

    /** "mm:ss Başlıq" sətirləri → [{t, title}] */
    public static function parseChapters(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/', trim($text)) as $line) {
            if (preg_match('/^\s*(?:(\d+):)?(\d{1,2}):(\d{2})\s+(.+)$/u', $line, $m)) {
                $out[] = ['t' => ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (int) $m[3], 'title' => ['az' => trim($m[4])]];
            }
        }

        return $out;
    }

    /** "Başlıq | https://..." sətirləri; yalnız https linkləri qəbul olunur. */
    public static function parseMaterials(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/', trim($text)) as $line) {
            [$title, $url] = array_map('trim', array_pad(explode('|', $line, 2), 2, ''));
            if ($title !== '' && filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'https://')) {
                $out[] = ['title' => mb_substr($title, 0, 200), 'url' => $url];
            }
        }

        return $out;
    }

    /** Admin brauzeri videonu birbaşa Bunny-yə (TUS) yükləyir — Netlify proxy-dən (26 san) keçmir. */
    public function uploadTicket(Request $request, Course $course, Lesson $lesson, VideoProvider $video)
    {
        $this->authorizeCourse($request, $course);
        abort_unless($lesson->course_id === $course->id, 404);
        $ticket = $video->createUpload($course->slug.' — '.$lesson->label());
        $asset = VideoAsset::create(['provider' => $video->name(), 'provider_video_id' => $ticket->providerVideoId,
            'title' => $lesson->label(), 'status' => 'uploading']);
        $this->versions->revise($lesson, ['video_asset_id' => $asset->id], $request->user(), 'video upload');

        return response()->json(['endpoint' => $ticket->endpoint, 'headers' => $ticket->headers, 'asset_id' => $asset->id]);
    }

    public function refreshVideo(Request $request, Course $course, Lesson $lesson, VideoProvider $video)
    {
        $this->authorizeCourse($request, $course);
        $asset = $lesson->revision?->video ?? abort(404);
        $s = $video->status($asset);
        $asset->update(['status' => $s['status'], 'duration_seconds' => $s['duration'] ?? $asset->duration_seconds]);
        Manifest::forget();

        return back()->with('status', __('admin.video_status').': '.$s['status']);
    }

    public function publish(Request $request, Course $course)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['apply_mode' => 'required|in:new_only,all', 'reason' => 'nullable|string|max:1000']);
        $this->versions->publish($this->draftOf($course), $data['apply_mode'], $data['reason'] ?? null, $request->user());

        return back()->with('status', __('admin.published'));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\AccessPolicy;
use App\Domain\Assessment\AssignmentService;
use App\Domain\Files\FileRejected;
use App\Domain\Files\FileService;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\Request;

class AssignmentAdminController extends Controller
{
    public function __construct(private AccessPolicy $access, private AssignmentService $svc) {}

    private function auth(Request $r, Course $c): void
    {
        abort_unless($this->access->isStaffFor($r->user(), $c), 403);
    }

    private static function tri(Request $r, string $f): array
    {
        return array_filter(['az' => trim((string) $r->input("{$f}_az")), 'en' => trim((string) $r->input("{$f}_en")), 'ru' => trim((string) $r->input("{$f}_ru"))], 'strlen');
    }

    public function store(Request $request, Course $course, Module $module)
    {
        $this->auth($request, $course);
        abort_unless($module->course_id === $course->id, 404);
        $request->validate(['title_az' => 'required|string|max:200', 'body_az' => 'required|string|max:20000',
            'max_points' => 'required|integer|min:1|max:1000', 'due_at' => 'nullable|date']);
        $draft = $course->draft() ?? abort(422);
        $this->svc->create($draft, $module, self::tri($request, 'title'), self::tri($request, 'body'), (int) $request->input('max_points'),
            $request->boolean('is_mandatory'), $request->input('due_at'), $request->user());

        return back()->with('status', __('ui.saved'));
    }

    public function edit(Request $request, Course $course, Assignment $assignment)
    {
        $this->auth($request, $course);
        abort_unless($assignment->course_id === $course->id, 404);
        $draft = $course->draft();
        $scoring = \DB::table('version_assignments')->where('assignment_id', $assignment->id)
            ->whereIn('course_version_id', array_filter([$draft?->id, $course->current_version_id]))->get()->keyBy('course_version_id');

        return view('admin.assignments.edit', ['course' => $course, 'a' => $assignment->load('revision'), 'draft' => $draft, 'scoring' => $scoring,
            'graded' => \DB::table('grades')->where('assignment_id', $assignment->id)->exists()]);
    }

    public function update(Request $request, Course $course, Assignment $assignment, FileService $files)
    {
        $this->auth($request, $course);
        abort_unless($assignment->course_id === $course->id, 404);
        $request->validate(['title_az' => 'required|string|max:200', 'body_az' => 'required|string|max:20000', 'due_at' => 'nullable|date', 'case_file' => 'nullable|file|max:6144']);
        $caseId = null;
        if ($f = $request->file('case_file')) {
            try {
                $caseId = $files->accept($request->user(), $course->id, 'case_file', $f->getRealPath(), $f->getClientOriginalName())->id;
            } catch (FileRejected $e) {
                return back()->withErrors(['case_file' => __('assess.file_rejected.'.$e->reason)]);
            }
        }
        $this->svc->reviseText($assignment, self::tri($request, 'title'), self::tri($request, 'body'), $request->input('due_at'), $caseId, $request->user());

        return back()->with('status', __('ui.saved'));
    }

    public function scoring(Request $request, Course $course, Assignment $assignment)
    {
        $this->auth($request, $course);
        abort_unless($assignment->course_id === $course->id, 404);
        $request->validate(['max_points' => 'required|integer|min:1|max:1000', 'reason' => 'nullable|string|max:1000']);
        $this->svc->setScoring($course->draft() ?? abort(422), $assignment, (int) $request->input('max_points'), $request->boolean('is_mandatory'),
            $request->user(), $request->input('reason'));

        return back()->with('status', __('ui.saved'));
    }
}

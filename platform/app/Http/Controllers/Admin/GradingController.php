<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\AccessPolicy;
use App\Domain\Assessment\GradingService;
use App\Domain\Certificates\DiplomaService;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Submission;
use Illuminate\Http\Request;

/** Tapşırıqların yoxlanması növbəsi. Təlimçi yalnız öz kursunun göndərişlərini görür (Policy + RLS). */
class GradingController extends Controller
{
    public function __construct(private AccessPolicy $access) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $courses = Course::all()->filter(fn ($c) => $this->access->isStaffFor($user, $c))->pluck('id');
        $status = in_array($request->query('status'), ['submitted', 'reviewing', 'graded', 'needs_revision'], true) ? $request->query('status') : 'submitted';
        $subs = Submission::with(['user', 'assignment.revision', 'assignment.course'])->whereIn('course_id', $courses)
            ->where('status', $status)->orderBy('submitted_at')->paginate(50)->withQueryString();

        return view('admin.grading.index', compact('subs', 'status'));
    }

    public function show(Request $request, Submission $submission)
    {
        abort_unless($this->access->isStaffFor($request->user(), $submission->assignment->course), 403);
        if ($submission->status === 'submitted') {
            $submission->update(['status' => 'reviewing']);
        }
        $e = $this->access->enrollment($submission->user, $submission->assignment->course);
        $max = $e ? \DB::table('version_assignments')->where(['course_version_id' => $e->course_version_id, 'assignment_id' => $submission->assignment_id])->value('max_points') : null;

        return view('admin.grading.show', [
            's' => $submission->load('files', 'user', 'assignment.revision'), 'max' => $max,
            'history' => Grade::where('submission_id', $submission->id)->orderBy('id')->get(),
            'audit' => AuditLog::where('entity_type', 'Submission')->where('entity_id', $submission->id)->orderBy('id')->get(),
        ]);
    }

    public function grade(Request $request, Submission $submission, GradingService $grading)
    {
        abort_unless($this->access->isStaffFor($request->user(), $submission->assignment->course), 403);
        $data = $request->validate(['points' => 'required|integer|min:0', 'feedback' => 'nullable|string|max:10000', 'status' => 'required|in:graded,needs_revision']);
        $grading->grade($submission, (int) $data['points'], isset($data['feedback']) ? strip_tags($data['feedback']) : null, $data['status'], $request->user());

        return redirect()->route('admin.grading.index')->with('status', __('assess.graded_flash'));
    }

    public function certificates(Request $request)
    {
        return view('admin.grading.certificates', ['certs' => Certificate::orderByDesc('issued_at')->paginate(50)]);
    }

    public function revoke(Request $request, Certificate $certificate, DiplomaService $d)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);
        $d->revoke($certificate, $data['reason'], $request->user());

        return back()->with('status', __('ui.saved'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Domain\Access\AccessPolicy;
use App\Domain\Assessment\AssignmentService;
use App\Domain\Assessment\Sanitizer;
use App\Domain\Certificates\DiplomaService;
use App\Domain\Files\FileRejected;
use App\Domain\Files\FileService;
use App\Models\Assignment;
use App\Models\BillingRule;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Submission;
use App\Models\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Ev tapşırıqları: yalnız ödənişli tələbə (və staff). Demo istifadəçi üçün bütün endpoint-lər 403. */
class AssignmentController extends Controller
{
    public function __construct(private AccessPolicy $access, private AssignmentService $assignments) {}

    private function guard(Request $r, Assignment $a): int
    {
        $user = $r->user();
        abort_unless($this->access->canSeePaidFeatures($user, $a->course), 403);
        if ($this->access->isStaffFor($user, $a->course)) {
            return (int) $a->course->current_version_id;
        }
        $v = $this->access->enrollment($user, $a->course)->course_version_id;
        abort_unless(DB::table('version_assignments')->where(['course_version_id' => $v, 'assignment_id' => $a->id])->exists(), 404);

        return $v;
    }

    public function show(Request $request, Assignment $assignment)
    {
        $v = $this->guard($request, $assignment);
        $row = $this->assignments->forVersion($v, userId: $request->user()->id)->firstWhere('id', $assignment->id);

        return response()->json([
            'id' => $row->id, 'title' => tr_field($row->title), 'body' => tr_field($row->body), 'max_points' => (int) $row->max_points,
            'mandatory' => (bool) $row->is_mandatory, 'due_at' => $row->due_at, 'status' => $row->submission->status ?? 'not_submitted',
            'grade' => $row->grade ? ['points' => (int) $row->grade->points, 'max' => (int) $row->grade->max_points_snapshot, 'feedback' => $row->grade->feedback] : null,
        ]);
    }

    public function submit(Request $request, Assignment $assignment, FileService $files)
    {
        $this->guard($request, $assignment);
        abort_if($this->access->isStaffFor($request->user(), $assignment->course) && ! $this->access->hasPaidAccess($request->user(), $assignment->course), 403);
        $rules = BillingRule::for($assignment->course_id);
        $data = $request->validate([
            'body' => 'nullable|string|max:20000',
            'files' => 'nullable|array|max:'.$rules->upload_files_per_submission,
            'files.*' => 'file|max:'.min(6144, $rules->upload_file_mb * 1024),
            'staged' => 'nullable|array|max:'.$rules->upload_files_per_submission,
            'staged.*' => 'uuid',
        ]);
        $user = $request->user();
        $existing = Submission::where('user_id', $user->id)->where('assignment_id', $assignment->id)->first();
        if ($existing && $existing->status === 'graded') {
            return back()->withErrors(['body' => __('assess.already_graded')]);
        }
        try {
            DB::transaction(function () use ($request, $assignment, $user, $data, $files, $existing, $rules) {
                $sub = $existing ?? new Submission(['user_id' => $user->id, 'course_id' => $assignment->course_id, 'assignment_id' => $assignment->id]);
                $sub->fill(['body_html' => Sanitizer::html($data['body'] ?? ''), 'status' => 'submitted', 'submitted_at' => now()])->save();
                $count = UploadedFile::where('submission_id', $sub->id)->count();
                foreach ($request->file('files', []) as $f) {
                    abort_if(++$count > $rules->upload_files_per_submission, 422);
                    $files->accept($user, $assignment->course_id, 'submission', $f->getRealPath(), $f->getClientOriginalName(), $sub->id);
                }
                // JS ilə hissə-hissə yüklənmiş fayllar (yalnız sahibinin, hələ bağlanmamış)
                foreach ($data['staged'] ?? [] as $id) {
                    abort_if(++$count > $rules->upload_files_per_submission, 422);
                    UploadedFile::where('id', $id)->where('owner_user_id', $user->id)->where('course_id', $assignment->course_id)
                        ->whereNull('submission_id')->update(['submission_id' => $sub->id]);
                }
            });
        } catch (FileRejected $e) {
            return back()->withInput()->withErrors(['files' => __('assess.file_rejected.'.$e->reason)]);
        }

        return back()->with('status', __('assess.submitted'));
    }

    // ---- Hissə-hissə yükləmə (Netlify proxy 26 san limiti üçün; 1 MB-lıq hissələr) ----
    public function uploadInit(Request $request, Assignment $assignment)
    {
        $this->guard($request, $assignment);
        $rules = BillingRule::for($assignment->course_id);
        $data = $request->validate(['name' => 'required|string|max:255', 'size' => 'required|integer|min:1|max:'.($rules->upload_file_mb * 1024 * 1024)]);
        $id = (string) Str::uuid();
        Cache::put("upload:{$id}", ['user' => $request->user()->id, 'assignment' => $assignment->id, 'name' => $data['name'], 'size' => (int) $data['size'], 'received' => 0], 3600);

        return response()->json(['upload_id' => $id, 'chunk_size' => 1024 * 1024], 201);
    }

    public function uploadChunk(Request $request, string $upload)
    {
        $meta = Cache::get("upload:{$upload}");
        abort_unless($meta && $meta['user'] === $request->user()->id && Str::isUuid($upload), 404);
        $offset = (int) $request->query('offset');
        $chunk = $request->getContent();
        abort_unless($offset === $meta['received'] && strlen($chunk) > 0 && strlen($chunk) <= 1024 * 1024 && $offset + strlen($chunk) <= $meta['size'], 409);
        Storage::disk('private')->append("tmp/{$upload}.part", $chunk, '');
        $meta['received'] += strlen($chunk);
        Cache::put("upload:{$upload}", $meta, 3600);

        return response()->json(['received' => $meta['received']]);
    }

    public function uploadComplete(Request $request, string $upload, FileService $files)
    {
        $meta = Cache::pull("upload:{$upload}");
        abort_unless($meta && $meta['user'] === $request->user()->id, 404);
        $assignment = Assignment::findOrFail($meta['assignment']);
        $this->guard($request, $assignment);
        $path = Storage::disk('private')->path("tmp/{$upload}.part");
        try {
            abort_unless($meta['received'] === $meta['size'], 422);
            $file = $files->accept($request->user(), $assignment->course_id, 'submission', $path, $meta['name']);
        } catch (FileRejected $e) {
            return response()->json(['error' => $e->reason, 'message' => __('assess.file_rejected.'.$e->reason)], 422);
        } finally {
            Storage::disk('private')->delete("tmp/{$upload}.part");
        }

        return response()->json(['file_id' => $file->id, 'status' => $file->scan_status], 201);
    }

    /** Profil / kurs üzrə ballar (diplom üçün 75% hədd). */
    public function scores(Request $request, Course $course, DiplomaService $d)
    {
        abort_unless($this->access->canSeePaidFeatures($request->user(), $course) || Certificate::where('user_id', $request->user()->id)->where('course_id', $course->id)->exists(), 403);
        $e = $this->access->enrollment($request->user(), $course);

        return response()->json($e ? $d->score($e) : null);
    }
}

<?php

namespace App\Http\Controllers;

use App\Domain\Access\AccessPolicy;
use App\Models\Course;
use App\Models\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Fayl endirmə: qısa ömürlü imzalı link (5 dəq) + sessiya + sahiblik yoxlaması.
 * Yalnız `clean` fayllar; pending/infected/error heç kimə verilmir. Content-Disposition: attachment, nosniff, sandbox.
 */
class FileController extends Controller
{
    public static function link(UploadedFile $f): string
    {
        return URL::temporarySignedRoute('files.download', now()->addMinutes(5), ['file' => $f->id]);
    }

    public function download(Request $request, string $file, AccessPolicy $access)
    {
        abort_unless($request->hasValidSignature(), 403);
        $f = UploadedFile::findOrFail($file);   // RLS: başqasının faylı tapılmır
        $user = $request->user();
        $course = Course::findOrFail($f->course_id);
        $allowed = $f->owner_user_id === $user->id
            || $access->isStaffFor($user, $course)
            || ($f->purpose === 'case_file' && $access->canSeePaidFeatures($user, $course));
        abort_unless($allowed, 403);
        abort_unless($f->isDownloadable(), 423, __('assess.file_not_ready'));
        $name = $f->original_name;
        $headers = [
            'Content-Type' => $f->mime_detected,
            'Content-Disposition' => 'attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/', '_', $name).'"; filename*=UTF-8\'\''.rawurlencode($name),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ];
        if (config('filesystems.accel_redirect')) {
            return response('', 200, $headers + ['X-Accel-Redirect' => '/_protected/'.$f->storage_key]);
        }

        return response()->file(Storage::disk('private')->path($f->storage_key), $headers);
    }
}

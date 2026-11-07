<?php

namespace App\Domain\Files;

use App\Domain\Audit\Audit;
use App\Domain\Notifications\Notifier;
use App\Domain\Notifications\Outbox;
use App\Models\BillingRule;
use App\Models\UploadedFile;
use App\Models\User;
use App\Support\System;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fayl qəbulu → private storage (veb kökündən kənar, random ad) → `pending` → antivirus skanı (worker) →
 * clean | infected (karantin + sahibinə bildiriş). Skan olunmayan fayl heç kimə endirilə bilməz.
 */
final class FileService
{
    public function __construct(private Scanner $scanner) {}

    public function accept(User $owner, int $courseId, string $purpose, string $tmpPath, string $originalName, ?int $submissionId = null): UploadedFile
    {
        $rules = BillingRule::for($courseId);
        $size = filesize($tmpPath);
        if ($size === 0 || $size > $rules->upload_file_mb * 1024 * 1024) {
            throw new FileRejected('size');
        }
        $used = (int) UploadedFile::where('owner_user_id', $owner->id)->where('course_id', $courseId)->whereNull('quarantined_at')->sum('size_bytes');
        if ($used + $size > $rules->upload_quota_mb * 1024 * 1024) {
            throw new FileRejected('quota');
        }
        $info = FileValidator::validate($tmpPath, $originalName);
        $id = (string) Str::uuid();
        $key = 'files/'.substr($id, 0, 2).'/'.$id.'.bin';  // uzantısız, icra oluna bilməyən ad
        Storage::disk('private')->put($key, fopen($tmpPath, 'rb'));
        $file = UploadedFile::create([
            'id' => $id, 'owner_user_id' => $owner->id, 'course_id' => $courseId, 'purpose' => $purpose,
            'storage_key' => $key, 'original_name' => self::cleanName($originalName, $info['ext']),
            'mime_detected' => $info['mime'], 'size_bytes' => $size, 'sha256' => hash_file('sha256', $tmpPath),
            'scan_status' => 'pending', 'submission_id' => $submissionId,
        ]);
        Outbox::record('file.scan', ['file_id' => $id]);

        return $file;
    }

    public static function cleanName(string $name, string $ext): string
    {
        $base = preg_replace('/[^\pL\pN ._()-]+/u', '_', pathinfo($name, PATHINFO_FILENAME));
        $base = trim(mb_substr((string) $base, 0, 120), ' ._') ?: 'file';

        return $base.'.'.$ext;
    }

    /** Worker: skan və karantin. */
    public function scan(string $fileId): void
    {
        System::run(function () use ($fileId) {
            $file = UploadedFile::find($fileId);
            if (! $file || $file->scan_status === 'clean' || $file->quarantined_at) {
                return;
            }
            $disk = Storage::disk('private');
            $r = $this->scanner->scan($disk->path($file->storage_key));
            if ($r['status'] === 'error') {
                $file->update(['scan_status' => 'error', 'scan_result' => $r['result']]);
                throw new \RuntimeException('scan failed: '.$r['result']); // outbox yenidən cəhd edir
            }
            DB::transaction(function () use ($file, $r, $disk) {
                if ($r['status'] === 'infected') {
                    $q = 'quarantine/'.basename($file->storage_key);
                    $disk->move($file->storage_key, $q);
                    $file->update(['scan_status' => 'infected', 'scan_result' => $r['result'], 'scanned_at' => now(),
                        'quarantined_at' => now(), 'storage_key' => $q]);
                    if ($owner = User::find($file->owner_user_id)) {
                        Notifier::send($owner, 'file_quarantined', ['file' => $file->original_name],
                            ['dedupe' => $file->id, 'priority' => Notifier::P_SECURITY]);
                    }
                    Audit::record('file.quarantined', null, after: ['file' => $file->id, 'signature' => $r['result']], entityType: 'UploadedFile');
                } else {
                    $file->update(['scan_status' => 'clean', 'scan_result' => 'OK', 'scanned_at' => now()]);
                }
            });
        });
    }
}

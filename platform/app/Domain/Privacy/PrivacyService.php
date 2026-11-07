<?php

namespace App\Domain\Privacy;

use App\Domain\Audit\Audit;
use App\Domain\Auth\SessionManager;
use App\Models\User;
use App\Support\DbContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * "Fərdi məlumatlar haqqında" Qanun (bölmə 2.14): məlumatların ixracı və hesabın silinməsi.
 * Silinmə = anonimləşdirmə: əlaqə məlumatları, qeydlər, cavablar və fayllar silinir; mühasibat üçün ödəniş qeydləri
 * anonim hesaba bağlı qalır; verilmiş diplomun açıq yoxlama qeydi (ad, kurs, tarix) qalır (bölmə 9.3-14 default).
 */
final class PrivacyService
{
    public function export(User $u): array
    {
        return DbContext::as($u->id, fn () => [
            'exported_at' => now()->toIso8601String(),
            'profile' => $u->only(['name', 'email', 'phone_e164', 'locale', 'created_at', 'email_verified_at', 'consent_at']),
            'demo' => DB::table('demo_claims')->where('user_id', $u->id)->first(['course_id', 'started_at', 'expires_at', 'converted_at']),
            'enrollments' => DB::table('enrollments')->where('user_id', $u->id)->get(['course_id', 'paid_until', 'canceled_at', 'modules_completed_at', 'mentoring_unlocked_at']),
            'progress' => DB::table('lesson_progress')->where('user_id', $u->id)->get(['lesson_id', 'position_seconds', 'watch_percent', 'completed_at']),
            'notes' => DB::table('personal_notes')->where('user_id', $u->id)->get(['lesson_id', 'at_second', 'body', 'created_at']),
            'submissions' => DB::table('submissions')->where('user_id', $u->id)->get(['assignment_id', 'status', 'body_html', 'submitted_at']),
            'grades' => DB::table('grades')->where('user_id', $u->id)->whereNull('superseded_at')->get(['assignment_id', 'points', 'max_points_snapshot', 'feedback', 'created_at']),
            'files' => DB::table('uploaded_files')->where('owner_user_id', $u->id)->get(['original_name', 'size_bytes', 'scan_status', 'created_at']),
            'certificates' => DB::table('certificates')->where('user_id', $u->id)->get(['verify_id', 'snapshot', 'issued_at', 'revoked_at']),
            'payments' => DB::table('payment_orders')->where('user_id', $u->id)->get(['course_id', 'amount_qepik', 'currency', 'status', 'created_at', 'paid_at']),
            'live_questions' => DB::table('live_questions')->where('user_id', $u->id)->get(['live_session_id', 'body', 'created_at']),
            'mentoring' => DB::table('mentor_bookings')->where('user_id', $u->id)->get(['slot_id', 'status', 'booked_at']),
            'sessions' => DB::table('user_sessions')->where('user_id', $u->id)->get(['created_at', 'last_seen_at', 'user_agent', 'revoked_at']),
        ]);
    }

    public function delete(User $u, SessionManager $sessions): void
    {
        abort_if($u->isStaff(), 422, 'staff accounts are deleted by an admin');
        DbContext::as($u->id, function () use ($u, $sessions) {
            DB::transaction(function () use ($u, $sessions) {
                $files = DB::table('uploaded_files')->where('owner_user_id', $u->id)->pluck('storage_key');
                DB::table('personal_notes')->where('user_id', $u->id)->delete();
                DB::table('live_questions')->where('user_id', $u->id)->delete();
                DB::table('submissions')->where('user_id', $u->id)->update(['body_html' => null]);
                DB::table('uploaded_files')->where('owner_user_id', $u->id)->delete();
                DB::table('subscriptions')->where('user_id', $u->id)->update(['provider_ref_encrypted' => null, 'status' => 'canceled', 'canceled_at' => now(), 'cancel_at_period_end' => true]);
                // Demo sui-istifadəsinin qarşısı üçün identifikatorlar açıq mətn yox, HMAC kimi saxlanılır
                $h = fn ($v) => 'h:'.substr(hash_hmac('sha256', (string) $v, (string) config('app.key')), 0, 40);
                $claim = DB::table('demo_claims')->where('user_id', $u->id)->first();
                if ($claim) {
                    DB::table('demo_claims')->where('id', $claim->id)->update(['email_normalized' => $h($claim->email_normalized), 'phone_e164' => substr($h($claim->phone_e164), 0, 20)]);
                }
                $sessions->revokeAll($u, 'account_deleted');
                $u->forceFill(['name' => 'Silinmiş istifadəçi', 'email' => 'deleted-'.$u->id.'@deleted.invalid', 'email_normalized' => 'deleted-'.$u->id,
                    'phone_e164' => null, 'password' => null, 'deleted_at' => now()])->save();
                Audit::record('privacy.account_deleted', $u, actorId: $u->id);
                DB::afterCommit(fn () => $files->each(fn ($k) => Storage::disk('private')->delete($k)));
            });
        });
    }
}

<?php

namespace App\Domain\Notifications;

use App\Mail\PlatformMail;
use App\Models\AppNotification;
use App\Support\System;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * E-mail göndərmə: prioritet sırası (demo linki birinci), SMTP limitinə uyğun throttle,
 * uğursuzluqda eksponensial gecikmə (2,4,8,16,32 dəq), sonda `failed` və admin görünüşü.
 */
final class NotificationSender
{
    public function run(int $max = 50): int
    {
        return System::run(function () use ($max) {
            $perMinute = (int) config('platform.mail.max_per_minute');
            $sentLastMinute = AppNotification::where('channel', 'email')->where('status', 'sent')
                ->where('sent_at', '>', now()->subMinute())->count();
            $budget = max(0, min($max, $perMinute - $sentLastMinute));
            $sent = 0;
            for ($i = 0; $i < $budget; $i++) {
                $result = DB::transaction(function () {
                    $n = AppNotification::where('channel', 'email')->where('status', 'pending')
                        ->where('next_attempt_at', '<=', now())
                        ->orderBy('priority')->orderBy('next_attempt_at')->orderBy('id')
                        ->lock('FOR UPDATE SKIP LOCKED')->first();
                    if (! $n) {
                        return null;
                    }
                    $this->deliver($n);

                    return true;
                });
                if ($result === null) {
                    break;
                }
                $sent++;
            }

            return $sent;
        });
    }

    public function deliver(AppNotification $n): void
    {
        $user = $n->user;
        if (! $user || $user->deleted_at) {
            $n->update(['status' => 'canceled', 'last_error' => 'user missing']);

            return;
        }
        try {
            Mail::to($user->email, $user->name)->send(new PlatformMail($n->event_type, $n->payload ?? [], $n->locale, $user->name));
            $n->update(['status' => 'sent', 'sent_at' => now(), 'attempts' => $n->attempts + 1, 'last_error' => null]);
        } catch (\Throwable $e) {
            $attempts = $n->attempts + 1;
            $failed = $attempts >= (int) config('platform.mail.max_attempts');
            $n->update([
                'attempts' => $attempts, 'status' => $failed ? 'failed' : 'pending',
                'next_attempt_at' => now()->addMinutes(2 ** $attempts),
                'last_error' => mb_substr($e->getMessage(), 0, 500),
            ]);
            Log::warning('mail send failed', ['notification' => $n->id, 'attempt' => $attempts]);
        }
    }

    /** Admin tərəfindən yenidən göndərmə (failed və ya sent). Audit log-u çağıran yazır. */
    public static function requeue(AppNotification $n): void
    {
        $n->update(['status' => 'pending', 'next_attempt_at' => now(), 'attempts' => 0, 'last_error' => null]);
    }
}

<?php

namespace App\Domain\Notifications;

use App\Models\OutboxEvent;
use App\Support\System;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Domen hadisələri üçün transactional outbox. Handler-lər `config/outbox.php`-dadır. */
final class Outbox
{
    public static function record(string $type, array $payload, ?\DateTimeInterface $availableAt = null): void
    {
        OutboxEvent::create(['event_type' => $type, 'payload' => $payload, 'available_at' => $availableAt ?? now()]);
    }

    public static function process(int $limit = 50): int
    {
        return System::run(function () use ($limit) {
            $done = 0;
            for ($i = 0; $i < $limit; $i++) {
                $ok = DB::transaction(function () {
                    $e = OutboxEvent::whereNull('processed_at')->where('available_at', '<=', now())
                        ->orderBy('id')->lock('FOR UPDATE SKIP LOCKED')->first();
                    if (! $e) {
                        return null;
                    }
                    $handler = config('outbox.handlers')[$e->event_type] ?? null;
                    try {
                        DB::transaction(fn () => $handler ? app($handler)->handle($e->payload) : null);
                        $e->update(['processed_at' => now(), 'last_error' => null]);
                    } catch (\Throwable $ex) {
                        $attempts = $e->attempts + 1;
                        $e->update(['attempts' => $attempts, 'last_error' => mb_substr($ex->getMessage(), 0, 1000),
                            'available_at' => now()->addMinutes(min(60, 2 ** $attempts)),
                            'processed_at' => $attempts >= 10 ? now() : null]);
                        Log::warning('outbox handler failed', ['type' => $e->event_type, 'id' => $e->id, 'error' => $ex->getMessage()]);
                    }

                    return true;
                });
                if (! $ok) {
                    break;
                }
                $done++;
            }

            return $done;
        });
    }
}

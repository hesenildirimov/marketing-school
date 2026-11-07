<?php

namespace App\Domain\Notifications;

use App\Models\AppNotification;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Vahid bildiriş modulu. Tətbiq kodu hadisəni bura verir; modul kanal, dil və planlaşdırmanı təyin edir.
 * E-mail sətri biznes tranzaksiyası ilə EYNİ tranzaksiyada yazılır (outbox pattern), göndərmə worker-dədir.
 * Təkrarlanma qarşısı: dedupe_key UNIQUE (event + entity + user + scheduled_for + kanal).
 */
final class Notifier
{
    /** Prioritetlər: 0 = demo/giriş linki, 1 = parol bərpası, 3 = ödəniş, 5 = adi */
    public const P_LOGIN_LINK = 0;

    public const P_SECURITY = 1;

    public const P_BILLING = 3;

    public const P_NORMAL = 5;

    /**
     * @param  array{entity?: array{0:string,1:int|null}, at?: CarbonInterface, priority?: int, channels?: string[], dedupe?: string}  $opt
     * @return int yaradılmış sətir sayı (dublikatlar 0)
     */
    public static function send(User $user, string $event, array $data = [], array $opt = []): int
    {
        [$etype, $eid] = $opt['entity'] ?? [null, null];
        // Tokenli linklər (təsdiq, parol bərpası) outbox-da açıq saxlanılmır: APP_KEY ilə şifrələnir (DB sızsa token açılmır)
        if (isset($data['url']) && preg_match('/[?&]t=/', (string) $data['url'])) {
            $data['url_enc'] = Crypt::encryptString($data['url']);
            unset($data['url']);
        }
        // Açıq planlanmış vaxt saniyə dəqiqliyi ilə saxlanılır; "indi" isə dəqiqəyə yuvarlaqlaşdırılır
        // ki, eyni hadisənin qısa fasilə ilə təkrar çağırılması dedupe ilə tutulsun.
        $at = isset($opt['at']) ? $opt['at']->copy()->utc()->startOfSecond() : now()->utc()->startOfMinute();
        $created = 0;
        foreach ($opt['channels'] ?? ['email', 'in_app'] as $channel) {
            $key = hash('sha256', implode('|', [$event, $etype, $eid, $user->id, $at->toIso8601String(), $channel, $opt['dedupe'] ?? '']));
            $created += DB::table('notifications')->insertOrIgnore([
                'user_id' => $user->id, 'channel' => $channel, 'event_type' => $event,
                'entity_type' => $etype, 'entity_id' => $eid, 'scheduled_for' => $at,
                'dedupe_key' => $key, 'locale' => in_array($user->locale, config('platform.locales'), true) ? $user->locale : 'az',
                'priority' => $opt['priority'] ?? self::P_NORMAL,
                'payload' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => $channel === 'in_app' ? 'sent' : 'pending',
                'sent_at' => $channel === 'in_app' ? $at : null,
                'next_attempt_at' => $at, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $created;
    }

    /** Ləğv olunan/dəyişən obyektin gözləyən xatırlatmalarını dayandırır. */
    public static function cancelPending(string $entityType, int $entityId, ?array $events = null): int
    {
        $q = AppNotification::where(['entity_type' => $entityType, 'entity_id' => $entityId]);
        if ($events) {
            $q->whereIn('event_type', $events);
        }
        // Gözləyən e-maillər (vaxtı çatmış, amma hələ göndərilməmişlər də) ləğv olunur
        $n = (clone $q)->where('channel', 'email')->where('status', 'pending')->update(['status' => 'canceled', 'updated_at' => now()]);
        // Gələcək üçün planlanmış platforma daxili bildirişlər gizlədilir
        (clone $q)->where('channel', 'in_app')->where('scheduled_for', '>', now())->update(['status' => 'canceled', 'updated_at' => now()]);

        return $n;
    }
}

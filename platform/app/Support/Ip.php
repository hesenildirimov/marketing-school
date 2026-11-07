<?php

namespace App\Support;

/** IP ünvanı log-a açıq yazılmır: APP_KEY ilə HMAC. */
final class Ip
{
    public static function hash(?string $ip): ?string
    {
        return $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null;
    }
}

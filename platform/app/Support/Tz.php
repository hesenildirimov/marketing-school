<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class Tz
{
    public static function local(?CarbonInterface $t): ?CarbonInterface
    {
        return $t?->copy()->setTimezone(config('platform.display_timezone'));
    }

    public static function fmt(?CarbonInterface $t, string $format = 'd.m.Y H:i'): string
    {
        return $t ? self::local($t)->format($format) : '';
    }
}

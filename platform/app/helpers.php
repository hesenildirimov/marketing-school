<?php

use App\Models\Course;
use App\Models\Price;
use Illuminate\Support\Facades\Schema;

/** Statik fayl URL-i, keş üçün versiya parametri ilə (fayl dəyişəndə yenilənir). */
function passet(string $path): string
{
    $file = public_path('platform/assets/'.$path);
    $v = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '0';

    return url('/platform/assets/'.$path).'?v='.$v;
}

function csp_nonce(): string
{
    return app()->bound('csp-nonce') ? app('csp-nonce') : '';
}

/** jsonb {az,en,ru} sahəsindən cari dil (yoxdursa az). */
function tr_field(mixed $value, ?string $locale = null): string
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : $value;
    }
    if (! is_array($value)) {
        return (string) $value;
    }
    $locale ??= app()->getLocale();

    return (string) ($value[$locale] ?? $value['az'] ?? reset($value) ?: '');
}

/** Kursun cari qiyməti (serverdəki prices cədvəlindən), məs. "99 AZN". */
function price_label(Course $course): string
{
    $p = Schema::hasTable('prices') ? Price::current($course->id) : null;

    return $p ? $p->display() : '99 AZN';
}

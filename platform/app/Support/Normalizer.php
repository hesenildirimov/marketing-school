<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/** Demo limitinin yoxlanması üçün e-mail və telefonun normallaşdırılması (bölmə 2.2). */
final class Normalizer
{
    private const GMAIL = ['gmail.com', 'googlemail.com'];

    public static function emailLower(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function email(string $email): string
    {
        $email = preg_replace('/\s+/u', '', self::emailLower($email));
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $local = explode('+', $local, 2)[0];
        if (in_array($domain, self::GMAIL, true)) {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local.'@'.$domain;
    }

    public static function domain(string $email): string
    {
        return substr(strrchr(self::emailLower($email), '@') ?: '', 1);
    }

    /** E.164; Azərbaycan yerli formatları (050..., 0 50..., +994 50..., 994...) eyni nəticə verir. */
    public static function phone(string $raw): ?string
    {
        $digits = preg_replace('/[^\d+]/', '', $raw);
        if (preg_match('/^994\d{9}$/', $digits)) {
            $digits = '+'.$digits;
        }
        try {
            $util = PhoneNumberUtil::getInstance();
            $num = $util->parse($digits, 'AZ');
            if (! $util->isValidNumber($num)) {
                return null;
            }

            return $util->format($num, PhoneNumberFormat::E164);
        } catch (NumberParseException) {
            return null;
        }
    }
}

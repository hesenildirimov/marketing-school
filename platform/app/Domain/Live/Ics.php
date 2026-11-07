<?php

namespace App\Domain\Live;

use Carbon\CarbonInterface;

/**
 * RFC 5545 iCalendar. Vaxt Asia/Baku zonasında yazılır (VTIMEZONE: UTC+4, 2016-dan yay vaxtı yoxdur),
 * dəyişiklikdə SEQUENCE artır, ləğvdə METHOD:CANCEL + STATUS:CANCELLED.
 */
final class Ics
{
    public static function event(string $uid, int $sequence, CarbonInterface $start, CarbonInterface $end, string $summary,
        string $description, string $url, string $method = 'REQUEST', string $organizerEmail = 'no-reply@marketingschool.az'): string
    {
        $tz = 'Asia/Baku';
        $fmt = fn (CarbonInterface $t) => $t->copy()->setTimezone($tz)->format('Ymd\THis');
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Marketing School//Platform//AZ', 'CALSCALE:GREGORIAN', 'METHOD:'.$method,
            'BEGIN:VTIMEZONE', 'TZID:'.$tz, 'BEGIN:STANDARD', 'DTSTART:19700101T000000', 'TZOFFSETFROM:+0400', 'TZOFFSETTO:+0400', 'TZNAME:+04', 'END:STANDARD', 'END:VTIMEZONE',
            'BEGIN:VEVENT', 'UID:'.$uid.'@marketingschool.az', 'SEQUENCE:'.$sequence, 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            "DTSTART;TZID={$tz}:".$fmt($start), "DTEND;TZID={$tz}:".$fmt($end),
            'SUMMARY:'.self::esc($summary), 'DESCRIPTION:'.self::esc($description), 'URL:'.$url,
            'ORGANIZER;CN=Marketing School:mailto:'.$organizerEmail,
            'STATUS:'.($method === 'CANCEL' ? 'CANCELLED' : 'CONFIRMED'), 'TRANSP:OPAQUE',
            'END:VEVENT', 'END:VCALENDAR',
        ];

        return implode("\r\n", array_map([self::class, 'fold'], $lines))."\r\n";
    }

    public static function esc(string $s): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $s);
    }

    /** 75 oktetdən uzun sətirlər bükülür (UTF-8 simvolu bölünmür). */
    public static function fold(string $line): string
    {
        $out = '';
        $limit = 75;                       // ilk sətir 75 oktet, davamlar boşluq + 74 oktet
        while (strlen($line) > $limit) {
            $cut = $limit;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $out .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
            $limit = 74;
        }

        return $out.$line;
    }
}

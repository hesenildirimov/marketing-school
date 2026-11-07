<?php

namespace Tests\Unit;

use App\Mail\PlatformMail;
use Tests\TestCase;

/** Bildiriş şablonları və UI mətnləri 3 dildə tamdır (bölmə 2.8, 2.13). */
class TranslationsTest extends TestCase
{
    private static function flatten(array $a, string $p = ''): array
    {
        $out = [];
        foreach ($a as $k => $v) {
            if (is_array($v) && ! array_is_list($v)) {
                $out += self::flatten($v, $p.$k.'.');
            } else {
                $out[$p.$k] = $v;
            }
        }

        return $out;
    }

    public function test_all_language_files_have_identical_keys(): void
    {
        foreach (glob(lang_path('az/*.php')) as $file) {
            $name = basename($file);
            $az = array_keys(self::flatten(require $file));
            foreach (['en', 'ru'] as $loc) {
                $other = lang_path("$loc/$name");
                $this->assertFileExists($other);
                $keys = array_keys(self::flatten(require $other));
                $this->assertSame([], array_values(array_diff($az, $keys)), "$loc/$name çatışmayan açarlar");
                $this->assertSame([], array_values(array_diff($keys, $az)), "$loc/$name artıq açarlar");
            }
            foreach (self::flatten(require $file) as $k => $v) {
                $this->assertNotSame('', $v, "boş tərcümə: az/$name $k");
            }
        }
    }

    public function test_every_notification_event_in_code_has_templates_in_three_languages(): void
    {
        $events = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        foreach ($it as $f) {
            if ($f->getExtension() !== 'php') {
                continue;
            }
            preg_match_all("/Notifier::send\\([^,]+,\\s*\\$?'?([a-z0-9_]+)'?/", file_get_contents($f->getPathname()), $m);
            $events = array_merge($events, $m[1]);
            preg_match_all("/(?:sendVerifyLink\\(\\\$\\w+, |broadcast\\(\\\$\\w+, |string \\\$event = )'([a-z0-9_]+)'/", file_get_contents($f->getPathname()), $m3);
            $events = array_merge($events, $m3[1]);
            preg_match_all("/(?:'|\")(live_reminder_24h|live_reminder_1h|mentoring_reminder_24h|mentoring_reminder_1h|renewal_upcoming|pay_link)(?:'|\")/", file_get_contents($f->getPathname()), $m2);
            $events = array_merge($events, $m2[1]);
        }
        $events = array_values(array_unique(array_filter($events, fn ($e) => ! in_array($e, ['event', 'auto'], true))));
        $this->assertGreaterThanOrEqual(29, count($events), 'bütün hadisələr tapılmalıdır: '.implode(',', $events));
        foreach (['az', 'en', 'ru'] as $loc) {
            $mail = require lang_path("$loc/mail.php");
            foreach ($events as $e) {
                $this->assertArrayHasKey($e, $mail, "$loc: mail.$e şablonu yoxdur");
                $this->assertNotEmpty($mail[$e]['subject'] ?? null, "$loc: mail.$e.subject");
                $this->assertNotEmpty($mail[$e]['lines'] ?? null, "$loc: mail.$e.lines");
            }
        }
        // Bölmə 2.8-dəki tam siyahı
        foreach (['demo_registered', 'demo_ending_soon', 'demo_ended', 'payment_succeeded', 'payment_failed', 'renewal_upcoming', 'grace_started', 'grace_ended',
            'assignment_graded', 'live_reminder_24h', 'live_reminder_1h', 'live_changed', 'live_canceled', 'mentoring_booked', 'mentoring_canceled', 'diploma_issued'] as $required) {
            $this->assertContains($required, $events, "2.8 hadisəsi kodda yoxdur: $required");
        }
    }

    public function test_every_mail_template_renders_in_each_language(): void
    {
        foreach (['az', 'en', 'ru'] as $loc) {
            foreach (array_keys(require lang_path("$loc/mail.php")) as $event) {
                if (in_array($event, ['greeting', 'footer'], true)) {
                    continue;
                }
                $html = (new PlatformMail($event, ['course' => 'Kurs', 'url' => 'https://x.test', 'when' => '14.11.2026 11:00', 'until' => '01.12.2026',
                    'amount' => '99 AZN', 'assignment' => 'A', 'points' => 8, 'max' => 10, 'file' => 'f.pdf', 'student' => 'S'], $loc, 'Ad'))->render();
                $this->assertStringNotContainsString(':course', $html, "$loc/$event");
                $this->assertStringContainsString('lang="'.$loc.'"', $html);
            }
        }
    }
}

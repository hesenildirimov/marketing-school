<?php

namespace Tests\Feature;

use App\Domain\Live\Ics;
use App\Domain\Live\LiveService;
use App\Domain\Notifications\NotificationSender;
use App\Mail\PlatformMail;
use App\Models\AppNotification;
use App\Models\DemoClaim;
use App\Models\LiveQuestion;
use App\Models\LiveSession;
use App\Models\VideoAsset;
use App\Support\DbContext;
use App\Support\System;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsCourses;
use Tests\TestCase;

/** Bölmə 7 — Canlı görüş testləri. */
class LiveTest extends TestCase
{
    use BuildsCourses;

    public function test_second_and_fourth_saturday_at_11_baku_by_calendar_not_week_number(): void
    {
        // 2026-11-01 bazar günüdür → 1-ci şənbə 7-si → 2-ci 14, 4-cü 28
        [$a, $b] = LiveService::occurrences(2026, 11);
        $this->assertSame('2026-11-14 11:00 Asia/Baku', $a->format('Y-m-d H:i e'));
        $this->assertSame('2026-11-28 11:00', $b->format('Y-m-d H:i'));
        $this->assertSame('07:00', $a->utc()->format('H:i'), 'UTC-də saxlanılır (Bakı = UTC+4)');
        // 2026-08-01 şənbədir → 2-ci 8, 4-cü 22
        [$c, $d] = LiveService::occurrences(2026, 8);
        $this->assertSame(['2026-08-08', '2026-08-22'], [$c->toDateString(), $d->toDateString()]);
        // 2027-05-01 şənbə; 2027-02 (28 gün) 1-i bazar ertəsi → 13 və 27
        $this->assertSame(['2027-02-13', '2027-02-27'], array_map(fn ($x) => $x->toDateString(), LiveService::occurrences(2027, 2)));
    }

    public function test_generation_is_idempotent_and_covers_three_months(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00', 'Asia/Baku'));
        ['course' => $c] = $this->buildCourse(modules: 1, lessonsPer: 1);
        $live = app(LiveService::class);
        $first = $live->generate($c);
        $this->assertSame(6, $first, 'noyabr 14/28, dekabr 12/26, yanvar 9/23');
        $this->assertSame(0, $live->generate($c), 'təkrar işə salınma dublikat yaratmır');
        $this->artisan('platform:live:generate')->assertSuccessful();
        $this->assertSame(6, LiveSession::where('course_id', $c->id)->count());
    }

    public function test_moved_session_is_not_recreated_by_generator(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00', 'Asia/Baku'));
        ['course' => $c] = $this->buildCourse(modules: 1, lessonsPer: 1);
        $live = app(LiveService::class);
        $live->generate($c);
        $s = LiveSession::orderBy('starts_at')->first();
        $live->update($s, CarbonImmutable::parse('2026-11-15 12:00', 'Asia/Baku'), 60, null, $this->makeUser([], ['admin']));
        $this->assertSame(0, $live->generate($c));
        $this->assertSame(0, LiveSession::where('starts_at', CarbonImmutable::parse('2026-11-14 11:00', 'Asia/Baku')->utc())->count());
    }

    public function test_change_notifies_paid_students_with_updated_ics_and_cancel_sends_method_cancel(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00', 'Asia/Baku'));
        ['course' => $c] = $this->buildCourse(modules: 1, lessonsPer: 1);
        $paid = $this->makeUser(['locale' => 'en']);
        $this->enroll($paid, $c);
        $expired = $this->makeUser();
        $this->enroll($expired, $c, now()->subMonth());
        $live = app(LiveService::class);
        $live->generate($c);
        $s = LiveSession::orderBy('starts_at')->first();
        $admin = $this->makeUser([], ['admin']);
        $live->update($s, CarbonImmutable::parse('2026-11-14 12:30', 'Asia/Baku'), 90, 'https://zoom.us/j/1', $admin);
        $n = System::run(fn () => AppNotification::where('event_type', 'live_changed')->where('channel', 'email')->get());
        $this->assertSame([$paid->id], $n->pluck('user_id')->all(), 'yalnız ödənişli tələbələr');
        $ics = $n[0]->payload['ics'];
        $this->assertStringContainsString("SEQUENCE:1\r\n", $ics);
        $this->assertStringContainsString('DTSTART;TZID=Asia/Baku:20261114T123000', $ics);
        $this->assertStringContainsString('DTEND;TZID=Asia/Baku:20261114T140000', $ics);
        $this->assertStringContainsString('UID:'.$s->ics_uid.'@marketingschool.az', $ics);
        $this->assertStringNotContainsString('zoom.us', $ics, 'görüş linki .ics-də yoxdur (serverdə qorunur)');

        $live->cancel($s->fresh(), $admin);
        $cn = System::run(fn () => AppNotification::where('event_type', 'live_canceled')->where('channel', 'email')->firstOrFail());
        $this->assertStringContainsString('METHOD:CANCEL', $cn->payload['ics']);
        $this->assertStringContainsString('STATUS:CANCELLED', $cn->payload['ics']);
        $this->assertStringContainsString("SEQUENCE:2\r\n", $cn->payload['ics']);
    }

    public function test_reminders_24h_and_1h_deduped_and_not_sent_for_canceled_session(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00', 'Asia/Baku'));
        ['course' => $c] = $this->buildCourse(modules: 1, lessonsPer: 1);
        $u = $this->makeUser();
        $this->enroll($u, $c, now()->addMonths(2));
        $live = app(LiveService::class);
        $live->generate($c);
        $this->travelTo(CarbonImmutable::parse('2026-11-13 11:02', 'Asia/Baku'));
        System::run(fn () => $live->scheduleReminders());
        System::run(fn () => $live->scheduleReminders());
        $rows = fn () => System::run(fn () => AppNotification::whereIn('event_type', ['live_reminder_24h', 'live_reminder_1h'])->get());
        $this->assertSame(['live_reminder_24h'], $rows()->pluck('event_type')->all(), 'təkrar işə salınma ikiqat yaratmır');
        $this->travelTo(CarbonImmutable::parse('2026-11-14 10:01', 'Asia/Baku'));
        System::run(fn () => $live->scheduleReminders());
        $this->assertSame(1, $rows()->where('event_type', 'live_reminder_1h')->count());

        // Ləğv: gözləyən xatırlatmalar dayanır və göndərilmir
        $s = LiveSession::orderBy('starts_at')->first();
        $live->cancel($s, $this->makeUser([], ['admin']));
        $this->assertSame(0, $rows()->where('status', 'pending')->count());
        Mail::fake();
        app(NotificationSender::class)->run();
        Mail::assertNotSent(PlatformMail::class, fn ($m) => str_starts_with($m->event, 'live_reminder'));
    }

    public function test_only_paid_students_see_live_page_link_and_recordings(): void
    {
        ['course' => $c] = $this->buildCourse(modules: 1, lessonsPer: 1);
        app(LiveService::class)->generate($c);
        $s = LiveSession::orderBy('starts_at')->first();
        $s->meeting_url_encrypted = 'https://zoom.us/j/secret-123';
        $s->recording_video_asset_id = VideoAsset::first()->id;
        $s->save();
        $this->assertStringNotContainsString('secret-123', DB::table('live_sessions')->where('id', $s->id)->value('meeting_url_encrypted'), 'link DB-də şifrələnib');

        $paid = $this->makeUser();
        $this->enroll($paid, $c);
        $this->signIn($paid)->get("/platform/courses/{$c->slug}/live")->assertOk()->assertSee('secret-123');
        $this->get("/platform/live/{$s->id}/ics")->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->postJson("/platform/api/live/{$s->id}/playback")->assertOk();

        $demo = $this->makeUser(['phone_e164' => '+994501230000']);
        System::run(fn () => DemoClaim::create(['user_id' => $demo->id, 'course_id' => $c->id, 'email_normalized' => $demo->email,
            'phone_e164' => '+994501230000', 'started_at' => now()->startOfSecond(), 'expires_at' => now()->startOfSecond()->addHours(24)]));
        foreach ([$demo, $this->makeUser()] as $u) {
            $this->signIn($u)->get("/platform/courses/{$c->slug}/live")->assertStatus(403)->assertDontSee('secret-123');
            $this->get("/platform/live/{$s->id}/ics")->assertStatus(403);
            $this->post("/platform/live/{$s->id}/questions", ['body' => 'sual?'])->assertStatus(403);
            $this->postJson("/platform/api/live/{$s->id}/playback")->assertStatus(403);
            $this->get("/platform/live/{$s->id}/recording")->assertStatus(403);
        }
    }

    public function test_questions_are_saved_and_private_to_author_and_staff(): void
    {
        ['course' => $c] = $this->buildCourse(modules: 1, lessonsPer: 1);
        app(LiveService::class)->generate($c);
        $s = LiveSession::where('starts_at', '>', now())->orderBy('starts_at')->first();
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->enroll($a, $c);
        $this->enroll($b, $c);
        $this->signIn($a)->post("/platform/live/{$s->id}/questions", ['body' => '<b>Brend</b> necə qurulur?'])->assertSessionHas('status');
        $this->assertSame('Brend necə qurulur?', LiveQuestion::first()->body);
        DbContext::actAs($b->id);
        $this->assertSame(0, DB::table('live_questions')->count());
        $t = $this->makeUser([], ['instructor']);
        DB::table('course_instructors')->insert(['course_id' => $c->id, 'user_id' => $t->id]);
        $this->signIn($t)->get("/platform/admin/live/{$s->id}")->assertOk()->assertSee('Brend necə qurulur?');
    }

    public function test_ics_folding_and_escaping(): void
    {
        $ics = Ics::event('u1', 0, now(), now()->addHour(), str_repeat('Rəqəmsal marketinq, SEO; ', 6), "Sətir1\nSətir2", 'https://x');
        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
        }
        $this->assertStringContainsString('\,', $ics);
        $this->assertStringContainsString('\;', $ics);
        $this->assertTrue(mb_check_encoding(str_replace("\r\n ", '', $ics), 'UTF-8'), 'UTF-8 simvolları bölünmür');
    }
}

<?php

namespace Tests\Feature;

use App\Domain\Notifications\NotificationSender;
use App\Domain\Notifications\Notifier;
use App\Domain\Notifications\Outbox;
use App\Mail\PlatformMail;
use App\Models\AppNotification;
use App\Models\OutboxEvent;
use App\Support\System;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    private function all()
    {
        return System::run(fn () => AppNotification::orderBy('id')->get());
    }

    public function test_dedupe_key_prevents_duplicate_email(): void
    {
        $u = $this->makeUser();
        $at = now()->addHour();
        $this->assertSame(1, Notifier::send($u, 'password_reset', [], ['channels' => ['email'], 'at' => $at, 'entity' => ['X', 5]]));
        $this->assertSame(0, Notifier::send($u, 'password_reset', [], ['channels' => ['email'], 'at' => $at, 'entity' => ['X', 5]]));
        $this->assertCount(1, $this->all());
    }

    public function test_sender_respects_priority_and_locale(): void
    {
        Mail::fake();
        $u = $this->makeUser(['locale' => 'en']);
        Notifier::send($u, 'account_exists', ['url' => 'https://x'], ['channels' => ['email'], 'priority' => 5]);
        Notifier::send($u, 'verify_email', ['url' => 'https://y'], ['channels' => ['email'], 'priority' => 0]);
        $order = [];
        Mail::assertNothingSent();
        app(NotificationSender::class)->run(1);
        Mail::assertSent(PlatformMail::class, function (PlatformMail $m) use (&$order) {
            $order[] = $m->event;

            return true;
        });
        $this->assertSame(['verify_email'], $order, 'demo/giriş linki birinci göndərilir');
        $mail = new PlatformMail('verify_email', ['url' => 'https://y'], 'en', 'Ann');
        $this->assertSame('Confirm your email — Marketing School', $mail->envelope()->subject);
        $this->assertStringContainsString('Confirm email', $mail->render());
    }

    public function test_smtp_failure_retries_with_backoff_then_fails_and_can_be_requeued(): void
    {
        $u = $this->makeUser();
        Notifier::send($u, 'verify_email', ['url' => 'https://y'], ['channels' => ['email']]);
        Mail::extend('boom', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $m): void
            {
                throw new \RuntimeException('SMTP down');
            }

            public function __toString(): string
            {
                return 'boom';
            }
        });
        config(['mail.mailers.boom' => ['transport' => 'boom'], 'mail.default' => 'boom']);
        $sender = app(NotificationSender::class);
        $sender->run();
        $n = $this->all()->first();
        $this->assertSame('pending', $n->status);
        $this->assertSame(1, $n->attempts);
        $this->assertTrue($n->next_attempt_at->gt(now()->addMinute()));
        $this->assertSame(0, $sender->run(), 'gecikmə bitmədən yenidən cəhd yoxdur');
        for ($i = 2; $i <= config('platform.mail.max_attempts'); $i++) {
            $this->travel(2 ** $i + 1)->minutes();
            $sender->run();
        }
        $n = $this->all()->first();
        $this->assertSame('failed', $n->status);

        config(['mail.default' => 'array']);
        Mail::forgetMailers();
        System::run(fn () => NotificationSender::requeue($n));
        $sender->run();
        $this->assertSame('sent', $this->all()->first()->status);
    }

    public function test_throttle_limits_emails_per_minute(): void
    {
        Mail::fake();
        config(['platform.mail.max_per_minute' => 2]);
        $u = $this->makeUser();
        foreach (range(1, 5) as $i) {
            Notifier::send($u, 'account_exists', [], ['channels' => ['email'], 'dedupe' => (string) $i]);
        }
        $this->assertSame(2, app(NotificationSender::class)->run());
        $this->assertSame(0, app(NotificationSender::class)->run());
        $this->travel(2)->minutes();
        $this->assertSame(2, app(NotificationSender::class)->run());
    }

    public function test_cancel_pending_stops_future_reminders(): void
    {
        $u = $this->makeUser();
        Notifier::send($u, 'reminder', [], ['entity' => ['LiveSession', 9], 'at' => now()->addDay()]);
        $this->assertSame(1, System::run(fn () => Notifier::cancelPending('LiveSession', 9)));
        $this->assertSame(['canceled', 'canceled'], $this->all()->pluck('status')->all());
    }

    public function test_outbox_retries_failed_handler(): void
    {
        config(['outbox.handlers' => array_merge(config('outbox.handlers'), ['test.boom' => BoomHandler::class])]);
        System::run(fn () => Outbox::record('test.boom', ['x' => 1]));
        BoomHandler::$fail = true;
        Outbox::process();
        $e = System::run(fn () => OutboxEvent::first());
        $this->assertNull($e->processed_at);
        $this->assertSame(1, $e->attempts);
        BoomHandler::$fail = false;
        $this->travel(5)->minutes();
        Outbox::process();
        $this->assertNotNull(System::run(fn () => OutboxEvent::first()->processed_at));
    }
}

class BoomHandler
{
    public static bool $fail = true;

    public function handle(array $p): void
    {
        if (self::$fail) {
            throw new \RuntimeException('boom');
        }
    }
}

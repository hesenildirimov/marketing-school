<?php

namespace Tests\Security;

use App\Domain\Auth\TwoFactor;
use App\Domain\Billing\PaymentProvider;
use Tests\Concerns\BuildsCourses;
use Tests\TestCase;

/** Bölmə 6: giriş, parol bərpası, demo, fayl yükləmə, video token, webhook, diplom yoxlaması — hamısı rate limit-lidir. */
class RateLimitTest extends TestCase
{
    use BuildsCourses;

    private function hitUntil429(callable $req, int $max): int
    {
        for ($i = 1; $i <= $max; $i++) {
            if ($req()->status() === 429) {
                return $i;
            }
        }

        return -1;
    }

    public function test_password_reset_is_rate_limited(): void
    {
        $n = $this->hitUntil429(fn () => $this->post('/platform/password/forgot', ['email' => 'x@mail.az']), 10);
        $this->assertSame(4, $n, '3/saat/e-mail');
    }

    public function test_video_token_and_progress_are_rate_limited(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse(modules: 1, lessonsPer: 1);
        $u = $this->makeUser();
        $this->enroll($u, $c);
        $this->signIn($u);
        $this->assertSame(31, $this->hitUntil429(fn () => $this->postJson("/platform/api/lessons/{$L[1][1]->id}/playback"), 40));
        $this->assertSame(31, $this->hitUntil429(fn () => $this->postJson("/platform/api/lessons/{$L[1][1]->id}/progress", ['position' => 1]), 40));
    }

    public function test_upload_is_rate_limited(): void
    {
        ['course' => $c] = $this->buildCourse(modules: 1, lessonsPer: 1);
        $u = $this->makeUser();
        $this->enroll($u, $c);
        $this->signIn($u);
        $n = $this->hitUntil429(fn () => $this->post('/platform/assignments/999999/submit', ['body' => 'x']), 40);
        $this->assertSame(31, $n);
    }

    public function test_webhook_endpoint_is_rate_limited(): void
    {
        config(['services.payment.provider' => 'fake']);
        app()->forgetInstance(PaymentProvider::class);
        $n = $this->hitUntil429(fn () => $this->call('POST', '/platform/api/payments/webhook/fake', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_FAKE_SIGNATURE' => 'bad'], '{}'), 130);
        $this->assertSame(121, $n);
    }

    public function test_two_factor_attempts_are_rate_limited(): void
    {
        $admin = $this->makeUser([], ['admin']);
        $tf = app(TwoFactor::class);
        $tf->begin($admin);
        $tf->confirm($admin);
        $this->signIn($admin, twoFactor: false);
        $this->assertSame(6, $this->hitUntil429(fn () => $this->post('/platform/2fa', ['code' => '000000']), 10));
    }
}

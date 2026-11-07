<?php

namespace App\Providers;

use App\Domain\Billing\PaymentProvider;
use App\Domain\Billing\Providers\EpointProvider;
use App\Domain\Billing\Providers\FakePaymentProvider;
use App\Domain\Billing\Providers\ManualProvider;
use App\Domain\Files\ClamdScanner;
use App\Domain\Files\Scanner;
use App\Domain\Video\BunnyStreamProvider;
use App\Domain\Video\FakeVideoProvider;
use App\Domain\Video\VideoProvider;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Scanner::class, fn () => new ClamdScanner(
            (string) config('services.clamav.host'), (int) config('services.clamav.port')));
        $this->app->singleton(PaymentProvider::class, fn () => match (config('services.payment.provider')) {
            'epoint' => new EpointProvider(config('services.epoint')),
            'fake' => new FakePaymentProvider,
            default => new ManualProvider,
        });
        $this->app->singleton(VideoProvider::class, fn () => match (config('services.video.provider')) {
            'fake' => new FakeVideoProvider,
            default => new BunnyStreamProvider(config('services.bunny')),
        });
    }

    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');
        // E-mail təsdiqlənəndə demo qeydində də qeyd olunur (demo müddəti dəyişmir).
        Event::listen('platform.email_verified', function (User $user) {
            DB::table('demo_claims')->where('user_id', $user->id)->whereNull('email_confirmed_at')->update(['email_confirmed_at' => now()]);
        });

        $email = fn (Request $r) => Normalizer::email((string) $r->input('email'));

        // Rate limit-lər (hamısı testlə yoxlanılır): IP üzrə və normallaşdırılmış e-mail üzrə ayrıca.
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by('login-e:'.$email($r)),
            Limit::perMinute(20)->by('login-ip:'.$r->ip()),
        ]);
        RateLimiter::for('register', fn (Request $r) => [
            Limit::perHour(3)->by('reg-e:'.$email($r)),
            Limit::perHour(10)->by('reg-ip:'.$r->ip()),
        ]);
        RateLimiter::for('forgot', fn (Request $r) => [
            Limit::perHour(3)->by('fg-e:'.$email($r)),
            Limit::perHour(10)->by('fg-ip:'.$r->ip()),
        ]);
        RateLimiter::for('token', fn (Request $r) => Limit::perMinute(10)->by('tok-ip:'.$r->ip()));
        RateLimiter::for('2fa', fn (Request $r) => Limit::perMinute(5)->by('2fa:'.$r->user()?->id));
        RateLimiter::for('demo', fn (Request $r) => [
            Limit::perHour(3)->by('demo-e:'.$email($r)),
            Limit::perHour(10)->by('demo-ip:'.$r->ip()),
        ]);
        RateLimiter::for('resend', fn (Request $r) => Limit::perHour((int) config('platform.auth.resend_limit_per_hour'))->by('resend:'.($r->user()?->id ?? $r->ip())));
        RateLimiter::for('upload', fn (Request $r) => Limit::perMinute(30)->by('up:'.$r->user()?->id));
        RateLimiter::for('video', fn (Request $r) => Limit::perMinute(30)->by('vid:'.$r->user()?->id));
        RateLimiter::for('progress', fn (Request $r) => Limit::perMinute(30)->by('prg:'.$r->user()?->id));
        RateLimiter::for('webhook', fn (Request $r) => Limit::perMinute(120)->by('wh:'.$r->ip()));
        RateLimiter::for('verify-diploma', fn (Request $r) => Limit::perMinute(20)->by('dv:'.$r->ip()));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by('api:'.($r->user()?->id ?? $r->ip())));
    }
}

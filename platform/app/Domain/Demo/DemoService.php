<?php

namespace App\Domain\Demo;

use App\Domain\Accounts\AccountRegistrar;
use App\Domain\Audit\Audit;
use App\Domain\Auth\TokenService;
use App\Domain\Notifications\Notifier;
use App\Models\Course;
use App\Models\DemoClaim;
use App\Models\User;
use App\Support\DbContext;
use App\Support\Ip;
use App\Support\Normalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Demo axını (bölmə 2.2):
 *  - platforma üzrə bir dəfə (demo_claims: UNIQUE user_id, email_normalized, phone_e164);
 *  - 24 saat QEYDİYYAT anından başlayır (e-mailə klikdən yox), resend/logout/gec açılış uzatmır;
 *  - hesab + demo_claim + e-mail (outbox) BİR tranzaksiyada;
 *  - HTTP cavabı həmişə eynidir (enumeration yoxdur); nəticə e-maillə bildirilir.
 */
final class DemoService
{
    public const OK = 'ok';

    public const EXISTING_ACCOUNT = 'existing_account';

    public const DEMO_USED = 'demo_used';

    public function __construct(private AccountRegistrar $registrar, private TokenService $tokens) {}

    public static function isDisposable(string $email): bool
    {
        $domain = Normalizer::domain($email);
        $parts = explode('.', $domain);
        $candidates = [];
        for ($i = 0; $i < count($parts) - 1; $i++) {
            $candidates[] = implode('.', array_slice($parts, $i));
        }

        return $candidates && DB::table('disposable_email_domains')->whereIn('domain', $candidates)->exists();
    }

    /** Landing formasından qeydiyyat. @return string nəticə kodu (yalnız daxili istifadə və testlər üçün) */
    public function register(string $name, string $email, string $phoneE164, Course $course, string $locale, ?string $ip): string
    {
        $email = Normalizer::emailLower($email);
        try {
            return DB::transaction(function () use ($name, $email, $phoneE164, $course, $locale, $ip) {
                $user = $this->registrar->create($name, $email, $phoneE164, $locale);
                try {
                    DB::transaction(fn () => $this->createClaim($user, $course, 'landing', $ip));
                } catch (UniqueConstraintViolationException) {
                    // Bu e-mail (normallaşdırılmış) və ya telefonla demo artıq istifadə olunub → hesab yaranır, demo yox.
                    $this->registrar->sendVerifyLink($user, 'demo_unavailable', ['course' => $course->title()]);

                    return self::DEMO_USED;
                }
                $this->registrar->sendVerifyLink($user, 'demo_registered', ['course' => $course->title()]);

                return self::OK;
            });
        } catch (UniqueConstraintViolationException) {
            // Bu e-maillə hesab artıq var (və ya paralel sorğu ilk oldu): yeni hesab/demo yaranmır.
            $existing = User::where('email', $email)->first();
            if ($existing) {
                DbContext::as($existing->id, fn () => Notifier::send($existing, 'demo_existing_account', ['url' => route('login')],
                    ['channels' => ['email'], 'priority' => Notifier::P_LOGIN_LINK, 'dedupe' => now()->format('YmdH')]));
            }

            return self::EXISTING_ACCOUNT;
        }
    }

    /** Daxil olmuş, hələ demo almamış istifadəçi demonu kabinetdən başladır. */
    public function startForUser(User $user, Course $course): ?DemoClaim
    {
        if (! $user->phone_e164) {
            return null;
        }
        try {
            return DB::transaction(fn () => $this->createClaim($user, $course, 'dashboard', request()->ip()));
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    private function createClaim(User $user, Course $course, string $source, ?string $ip): DemoClaim
    {
        $now = now()->startOfSecond();
        $claim = DemoClaim::create([
            'user_id' => $user->id, 'course_id' => $course->id,
            'email_normalized' => Normalizer::email($user->email), 'phone_e164' => $user->phone_e164,
            'started_at' => $now, 'expires_at' => $now->copy()->addHours(24), 'source' => $source,
            'ip_hash' => Ip::hash($ip), 'email_confirmed_at' => $user->email_verified_at,
        ]);
        $entity = ['DemoClaim', $claim->id];
        Notifier::send($user, 'demo_ending_soon', ['course' => $course->title(), 'url' => route('course.show', $course)],
            ['entity' => $entity, 'at' => $claim->expires_at->copy()->subHours(6)]);
        Notifier::send($user, 'demo_ended', ['course' => $course->title(), 'url' => route('course.show', $course)],
            ['entity' => $entity, 'at' => $claim->expires_at]);
        Audit::record('demo.started', $claim, after: ['course_id' => $course->id, 'source' => $source], actorId: $user->id);

        return $claim;
    }

    /** Linkin yenidən göndərilməsi: köhnə link etibarsızlaşır, demo müddəti DƏYİŞMİR, yeni demo yaranmır. */
    public function resend(User $user, bool $byAdmin = false): bool
    {
        if ($user->email_verified_at) {
            return false;
        }
        if (! $byAdmin && $this->tokens->recentCount($user, TokenService::VERIFY) >= config('platform.auth.resend_limit_per_hour')) {
            return false;
        }
        DbContext::as($user->id, function () use ($user) {
            $claim = DemoClaim::where('user_id', $user->id)->first();
            $this->registrar->sendVerifyLink($user, $claim ? 'demo_registered' : 'verify_email', $claim ? ['course' => $claim->course->title()] : []);
        });

        return true;
    }

    /** Ödəniş təsdiqlənəndə (M6): eyni user_id; progress və qeydlər user+lesson-a bağlıdır, toxunulmur. */
    public static function markConverted(User $user, Course $course): void
    {
        $claim = DemoClaim::where('user_id', $user->id)->first();
        if (! $claim) {
            return;
        }
        if ((int) $claim->course_id === $course->id) {
            $claim->converted_at ??= now();
        } else {
            $claim->converted_other_at ??= now();
        }
        $claim->save();
        Notifier::cancelPending('DemoClaim', $claim->id);
    }
}

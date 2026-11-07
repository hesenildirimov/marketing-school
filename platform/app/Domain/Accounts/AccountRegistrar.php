<?php

namespace App\Domain\Accounts;

use App\Domain\Auth\TokenService;
use App\Domain\Notifications\Notifier;
use App\Models\User;
use App\Support\DbContext;
use App\Support\Normalizer;

/**
 * Təsdiqlənməmiş hesab + təsdiq linki + e-mail (outbox) yaradır. Çağıran tərəf tranzaksiya açır.
 * Parol sonradan, e-mail linki ilə təyin olunur.
 */
final class AccountRegistrar
{
    public function __construct(private TokenService $tokens) {}

    public function create(string $name, string $email, ?string $phoneE164, string $locale): User
    {
        $email = Normalizer::emailLower($email);
        $user = User::create([
            'name' => trim($name), 'email' => $email, 'email_normalized' => Normalizer::email($email),
            'phone_e164' => $phoneE164, 'locale' => in_array($locale, config('platform.locales'), true) ? $locale : 'az',
            'short_code' => User::newShortCode(), 'consent_at' => now(),
        ]);
        $user->grantRole('student');
        DbContext::actAs($user->id);

        return $user;
    }

    /** Yeni təsdiq linki (köhnələr etibarsızlaşır) və ən yüksək prioritetli e-mail. */
    public function sendVerifyLink(User $user, string $event = 'verify_email', array $extra = []): void
    {
        $raw = $this->tokens->issue($user, TokenService::VERIFY, now()->addHours(config('platform.auth.email_token_ttl_hours')));
        Notifier::send($user, $event, array_merge($extra, ['url' => route('verify.show', ['t' => $raw])]), [
            'priority' => Notifier::P_LOGIN_LINK, 'channels' => ['email'], 'dedupe' => TokenService::hash($raw),
        ]);
    }
}

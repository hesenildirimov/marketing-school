<?php

namespace App\Domain\Auth;

use App\Models\AuthToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Birdəfəlik e-mail tokenləri (təsdiq, parol bərpası).
 * - 32 bayt kriptoqrafik təsadüfi, DB-də yalnız sha256 hash;
 * - yeni token köhnəni etibarsız edir; istifadə FOR UPDATE ilə atomikdir.
 */
final class TokenService
{
    public const VERIFY = 'email_verify';

    public const RESET = 'password_reset';

    public function issue(User $user, string $purpose, \DateTimeInterface $expiresAt): string
    {
        $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        DB::transaction(function () use ($user, $purpose, $raw, $expiresAt) {
            AuthToken::where(['user_id' => $user->id, 'purpose' => $purpose])
                ->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            AuthToken::create([
                'user_id' => $user->id, 'purpose' => $purpose,
                'token_hash' => self::hash($raw), 'expires_at' => $expiresAt,
            ]);
        });

        return $raw;
    }

    /** Tokeni istehlak etmədən yoxlayır (GET forması üçün). */
    public function peek(string $raw, string $purpose): ?AuthToken
    {
        $t = AuthToken::where('token_hash', self::hash($raw))->where('purpose', $purpose)->first();

        return $t && $this->usable($t) ? $t : null;
    }

    /** Atomik istehlak; çağıran tərəf tranzaksiya daxilində olmalıdır. */
    public function consume(string $raw, string $purpose): ?AuthToken
    {
        $t = AuthToken::where('token_hash', self::hash($raw))->where('purpose', $purpose)->lockForUpdate()->first();
        if (! $t || ! $this->usable($t)) {
            return null;
        }
        $t->update(['used_at' => now()]);

        return $t;
    }

    public function recentCount(User $user, string $purpose, int $minutes = 60): int
    {
        return AuthToken::where(['user_id' => $user->id, 'purpose' => $purpose])
            ->where('created_at', '>', now()->subMinutes($minutes))->count();
    }

    private function usable(AuthToken $t): bool
    {
        return $t->used_at === null && $t->revoked_at === null && $t->expires_at->isFuture();
    }

    public static function hash(string $raw): string
    {
        return hash('sha256', $raw);
    }
}

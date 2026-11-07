<?php

namespace App\Domain\Auth;

use App\Models\RecoveryCode;
use App\Models\TwoFactorSecret;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

/** TOTP (RFC 6238) — admin və təlimçi üçün məcburi; recovery code-lar hash ilə, birdəfəlik. */
final class TwoFactor
{
    public function __construct(private Google2FA $g = new Google2FA) {}

    public function begin(User $user): TwoFactorSecret
    {
        $existing = TwoFactorSecret::find($user->id);
        if ($existing && $existing->confirmed_at) {
            return $existing;
        }

        return TwoFactorSecret::updateOrCreate(['user_id' => $user->id],
            ['secret_encrypted' => $this->g->generateSecretKey(32), 'confirmed_at' => null, 'last_used_step' => null]);
    }

    public function qrSvg(User $user, TwoFactorSecret $s): string
    {
        $uri = $this->g->getQRCodeUrl('Marketing School', $user->email, $s->secret_encrypted);

        return (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))->writeString($uri);
    }

    /** Kodu yoxlayır; eyni zaman addımının kodu ikinci dəfə qəbul edilmir (replay qoruması). */
    public function verify(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code) {
            $s = TwoFactorSecret::where('user_id', $user->id)->lockForUpdate()->first();
            if (! $s || ! preg_match('/^\d{6}$/', $code = preg_replace('/\s+/', '', $code))) {
                return false;
            }
            $step = $this->g->verifyKeyNewer($s->secret_encrypted, $code, $s->last_used_step, 1);
            if ($step === false) {
                return false;
            }
            $s->last_used_step = is_int($step) ? $step : (int) floor(time() / 30);
            $s->save();

            return true;
        });
    }

    /** @return string[] açıq mətnli kodlar (yalnız bir dəfə göstərilir) */
    public function confirm(User $user): array
    {
        TwoFactorSecret::where('user_id', $user->id)->update(['confirmed_at' => now()]);

        return $this->regenerateRecoveryCodes($user);
    }

    public function regenerateRecoveryCodes(User $user): array
    {
        RecoveryCode::where('user_id', $user->id)->delete();
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $code = strtolower(bin2hex(random_bytes(4)).'-'.bin2hex(random_bytes(4)));
            RecoveryCode::create(['user_id' => $user->id, 'code_hash' => self::hashCode($code)]);
            $codes[] = $code;
        }

        return $codes;
    }

    public function useRecoveryCode(User $user, string $code): bool
    {
        return RecoveryCode::where('user_id', $user->id)->where('code_hash', self::hashCode(strtolower(trim($code))))
            ->whereNull('used_at')->update(['used_at' => now()]) === 1;
    }

    public function isConfirmed(User $user): bool
    {
        return TwoFactorSecret::where('user_id', $user->id)->whereNotNull('confirmed_at')->exists();
    }

    public static function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}

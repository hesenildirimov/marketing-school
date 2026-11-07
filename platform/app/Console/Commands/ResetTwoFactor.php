<?php

namespace App\Console\Commands;

use App\Domain\Audit\Audit;
use App\Domain\Auth\SessionManager;
use App\Models\User;
use App\Support\Normalizer;
use App\Support\System;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Telefonunu və recovery kodlarını itirmiş admin/təlimçi üçün 2FA sıfırlanması (yalnız server CLI-dən).
 * Bütün sessiyalar ləğv olunur; növbəti girişdə 2FA yenidən qurulmalıdır. Səbəb audit-ə yazılır.
 */
class ResetTwoFactor extends Command
{
    protected $signature = 'platform:user:reset-2fa {email} {--reason= : məcburi səbəb (audit üçün)}';

    protected $description = '2FA sirrini və recovery kodlarını silir, sessiyaları ləğv edir';

    public function handle(SessionManager $sessions): int
    {
        $reason = trim((string) $this->option('reason'));
        if (mb_strlen($reason) < 5) {
            $this->error('--reason məcburidir (min. 5 simvol)');

            return self::FAILURE;
        }

        return System::run(function () use ($sessions, $reason) {
            $user = User::where('email', Normalizer::emailLower($this->argument('email')))->first();
            if (! $user) {
                $this->error('istifadəçi tapılmadı');

                return self::FAILURE;
            }
            DB::transaction(function () use ($user, $sessions, $reason) {
                DB::table('two_factor_secrets')->where('user_id', $user->id)->delete();
                DB::table('recovery_codes')->where('user_id', $user->id)->delete();
                $sessions->revokeAll($user, '2fa_reset');
                Audit::record('user.2fa_reset', $user, reason: 'CLI: '.$reason);
            });
            $this->info("2FA sıfırlandı: {$user->email}");

            return self::SUCCESS;
        });
    }
}

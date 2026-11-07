<?php

namespace Tests\Feature;

use App\Domain\Auth\TwoFactor;
use App\Models\AuditLog;
use App\Support\System;
use Tests\TestCase;

class ResetTwoFactorTest extends TestCase
{
    public function test_cli_reset_removes_secret_codes_and_sessions(): void
    {
        $admin = $this->makeUser(['email' => 'lost@example.com'], ['admin']);
        System::run(function () use ($admin) {
            $tf = app(TwoFactor::class);
            $tf->begin($admin);
            $tf->confirm($admin);
        });
        $this->post('/platform/login', ['email' => 'lost@example.com', 'password' => 'Secret12345']);

        $this->artisan('platform:user:reset-2fa', ['email' => 'lost@example.com'])->assertFailed();
        $this->artisan('platform:user:reset-2fa', ['email' => 'LOST@example.com', '--reason' => 'telefon itib, şəxsiyyət yoxlanıldı'])->assertSuccessful();

        System::run(function () use ($admin) {
            $this->assertSame(0, \DB::table('two_factor_secrets')->where('user_id', $admin->id)->count());
            $this->assertSame(0, \DB::table('recovery_codes')->where('user_id', $admin->id)->count());
            $this->assertSame(0, \DB::table('user_sessions')->where('user_id', $admin->id)->whereNull('revoked_at')->count());
            $this->assertTrue(AuditLog::where('action', 'user.2fa_reset')->where('entity_id', $admin->id)->exists());
        });
    }
}

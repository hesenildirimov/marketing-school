<?php

namespace Tests;

use App\Models\User;
use App\Models\UserSession;
use App\Support\DbContext;
use App\Support\Normalizer;
use App\Support\System;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Testlər real PostgreSQL üzərində işləyir: veb tətbiqi `ms_app` (RLS-ə tabe) rolu ilə,
 * miqrasiyalar `ms_owner` ilə. Hər testdən əvvəl cədvəllər TRUNCATE olunur (paralel proses
 * testləri üçün tranzaksiya geri qaytarması istifadə olunmur).
 */
abstract class TestCase extends BaseTestCase
{
    private static bool $migrated = false;

    private const KEEP = ['migrations', 'disposable_email_domains'];

    protected function setUp(): void
    {
        parent::setUp();
        if (! self::$migrated) {
            Artisan::call('migrate:fresh', ['--database' => 'pgsql_owner', '--force' => true]);
            self::$migrated = true;
        } else {
            $this->truncateAll();
        }
        DbContext::actAs(null);
    }

    /** Hər HTTP sorğusu production-dakı kimi təmiz DB konteksti ilə başlayır (PHP-FPM: yeni bağlantı). */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        DbContext::actAs(null);

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    protected function truncateAll(): void
    {
        $owner = DB::connection('pgsql_owner');
        $tables = collect($owner->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'"))
            ->pluck('tablename')->diff(self::KEEP)->map(fn ($t) => '"'.$t.'"')->implode(', ');
        if ($tables) {
            $owner->statement("TRUNCATE {$tables} RESTART IDENTITY CASCADE");
        }
        if (method_exists($this, 'seedReference')) {
            $this->seedReference();
        }
        ReferenceSeeder::runAsOwner();
    }

    protected function makeUser(array $attrs = [], array $roles = ['student'], bool $verified = true, string $password = 'Secret12345'): User
    {
        $email = $attrs['email'] ?? 'u'.bin2hex(random_bytes(4)).'@example.com';
        $user = User::create(array_merge([
            'name' => 'Test User', 'email' => Normalizer::emailLower($email), 'email_normalized' => Normalizer::email($email),
            'phone_e164' => null, 'locale' => 'az', 'short_code' => User::newShortCode(),
            'email_verified_at' => $verified ? now() : null, 'password' => $password,
        ], $attrs));
        foreach ($roles as $r) {
            $user->grantRole($r);
        }

        return $user;
    }

    /** Real server-side sessiya ilə giriş (cihaz limiti, ləğv və 2FA middleware-ləri işləyir). */
    protected function signIn(User $user, bool $twoFactor = true): static
    {
        $raw = bin2hex(random_bytes(32));
        System::run(fn () => UserSession::create([
            'id' => hash('sha256', $raw), 'user_id' => $user->id, 'created_at' => now(), 'last_seen_at' => now(),
            'two_factor_at' => $twoFactor ? now() : null, 'reauth_at' => now(),
        ]));
        $this->actingAs($user);

        return $this->withSession(['auth_sid' => $raw]);
    }

    protected function asSystem(callable $fn): mixed
    {
        return System::run($fn);
    }
}

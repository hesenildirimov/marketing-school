<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Cari istifadəçini PostgreSQL sessiyasına yazır (RLS üçün). Hər HTTP sorğusunun əvvəlində
 * middleware çağırır. PHP-FPM bağlantıları persistent deyil, buna görə dəyər sorğular arasında sızmır;
 * buna baxmayaraq hər sorğuda dəyər yenidən yazılır (qonaq üçün boş).
 */
final class DbContext
{
    private static ?int $current = null;

    public static function actAs(?int $userId): void
    {
        self::$current = $userId;
        DB::select("SELECT set_config('app.user_id', ?, false)", [$userId === null ? '' : (string) $userId]);
    }

    public static function current(): ?int
    {
        return self::$current;
    }

    /** Müvəqqəti olaraq başqa istifadəçi adından (məs. webhook həmin sifarişin sahibi kimi). */
    public static function as(?int $userId, callable $fn): mixed
    {
        $prev = self::$current;
        self::actAs($userId);
        try {
            return $fn();
        } finally {
            self::actAs($prev);
        }
    }
}

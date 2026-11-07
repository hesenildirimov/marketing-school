<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL Row-Level Security köməkçisi (yalnız miqrasiyalarda, ms_owner ilə).
 * RLS tətbiq səviyyəsindəki Policy qatını ƏVƏZ ETMİR, əlavə qoruma qatıdır.
 */
final class Rls
{
    public static function appRole(): string
    {
        return self::ident(config('database.connections.pgsql.username', 'ms_app'));
    }

    public static function workerRole(): string
    {
        return self::ident(config('database.connections.pgsql_worker.username', 'ms_worker'));
    }

    /**
     * @param  string  $using  SELECT/UPDATE/DELETE üçün şərt
     * @param  string|null  $insertCheck  INSERT üçün ayrıca şərt (null: $using ilə eyni)
     */
    public static function enable(string $table, string $using, ?string $insertCheck = null): void
    {
        $t = self::ident($table);
        DB::statement("ALTER TABLE {$t} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$t} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS {$t}_select ON {$t}");
        DB::statement("DROP POLICY IF EXISTS {$t}_insert ON {$t}");
        DB::statement("DROP POLICY IF EXISTS {$t}_update ON {$t}");
        DB::statement("DROP POLICY IF EXISTS {$t}_delete ON {$t}");
        DB::statement("CREATE POLICY {$t}_select ON {$t} FOR SELECT USING ({$using})");
        DB::statement("CREATE POLICY {$t}_insert ON {$t} FOR INSERT WITH CHECK (".($insertCheck ?? $using).')');
        DB::statement("CREATE POLICY {$t}_update ON {$t} FOR UPDATE USING ({$using}) WITH CHECK ({$using})");
        DB::statement("CREATE POLICY {$t}_delete ON {$t} FOR DELETE USING ({$using})");
    }

    private static function ident(string $name): string
    {
        if (! preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException("Invalid identifier: {$name}");
        }

        return $name;
    }
}

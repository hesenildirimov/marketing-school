<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Sistem işləri (worker, scheduler, bildiriş göndərmə) ms_worker DB rolu ilə işləyir.
 * Veb prosesində ms_worker parolu yoxdur (production-da yalnız worker konteynerinin .env-ində).
 */
final class System
{
    public static function run(callable $fn): mixed
    {
        $prev = DB::getDefaultConnection();
        if ($prev === 'pgsql_worker') {
            return $fn();
        }
        DB::setDefaultConnection('pgsql_worker');
        try {
            return $fn();
        } finally {
            DB::setDefaultConnection($prev);
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** İstinad məlumatları (billing qaydaları, qiymətlər) — miqrasiyalarda da yazılır; testlərdə hər dəfə bərpa olunur. */
class ReferenceSeeder extends Seeder
{
    public static function runAsOwner(): void
    {
        foreach (glob(database_path('seeders/reference/*.php')) as $file) {
            (require $file)();
        }
    }

    public function run(): void
    {
        self::runAsOwner();
    }
}

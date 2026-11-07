<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Birdəfəlik e-mail domenləri (ilkin siyahı). Yeniləmə: php artisan platform:disposable:import <fayl>
return new class extends Migration
{
    protected $connection = 'pgsql_owner';

    public function up(): void
    {
        $domains = preg_split('/\s+/', trim(file_get_contents(database_path('data/disposable_domains.txt'))));
        foreach (array_chunk(array_unique(array_filter($domains)), 200) as $chunk) {
            DB::table('disposable_email_domains')->insertOrIgnore(array_map(fn ($d) => ['domain' => strtolower($d), 'source' => 'seed'], $chunk));
        }
    }

    public function down(): void
    {
        DB::table('disposable_email_domains')->where('source', 'seed')->delete();
    }
};

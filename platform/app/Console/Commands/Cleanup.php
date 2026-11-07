<?php

namespace App\Console\Commands;

use App\Support\System;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Məlumatların minimallaşdırılması və disk qoruması (bölmə 2.14, 2.5):
 * yarımçıq yükləmələr (2 saat), istifadə olunmuş/vaxtı keçmiş tokenlər (30 gün), ləğv olunmuş sessiyalar (90 gün),
 * video token jurnalı (90 gün), Laravel sessiya payload-ları (müddəti keçmiş).
 */
class Cleanup extends Command
{
    protected $signature = 'platform:cleanup';

    protected $description = 'Köhnə müvəqqəti məlumatların silinməsi (retention)';

    public function handle(): int
    {
        $disk = Storage::disk('private');
        $tmp = collect($disk->files('tmp'))->filter(fn ($f) => $disk->lastModified($f) < now()->subHours(2)->timestamp);
        $tmp->each(fn ($f) => $disk->delete($f));
        $out = System::run(fn () => [
            'tmp_uploads' => $tmp->count(),
            'auth_tokens' => DB::table('auth_tokens')->where('expires_at', '<', now()->subDays(30))->delete(),
            'user_sessions' => DB::table('user_sessions')->whereNotNull('revoked_at')->where('revoked_at', '<', now()->subDays(90))->delete(),
            'video_token_log' => DB::table('video_token_log')->where('created_at', '<', now()->subDays(90))->delete(),
            'sessions' => DB::table('sessions')->where('last_activity', '<', now()->subMinutes((int) config('session.lifetime'))->timestamp)->delete(),
        ]);
        $this->line(json_encode($out));

        return self::SUCCESS;
    }
}

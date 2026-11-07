<?php

namespace App\Domain\Video;

use App\Domain\Access\AccessPolicy;
use App\Domain\Audit\Audit;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Models\VideoAsset;
use App\Support\Ip;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Video tokeni yalnız icazə yoxlanandan sonra verilir (bölmə 2.3):
 * - TTL 10 dəq, amma demo üçün demo_expires_at-dan, ödənişli üçün grace_until-dən uzun ola bilməz;
 * - yeniləmə (refresh) zamanı icazə YENİDƏN yoxlanılır — demo bitəndə və ya enrollment expired olanda izləmə kəsilir;
 * - hər buraxılış log-a yazılır, qeyri-adi həcm xəbərdarlıq yaradır.
 */
final class PlaybackService
{
    public function __construct(private AccessPolicy $access, private VideoProvider $video) {}

    /** @return array{src:string, expires_at:string, refresh_in:int}|null */
    public function grant(User $user, Course $course, Lesson $lesson, string $kind = 'issue'): ?array
    {
        $ok = $lesson->course_id === $course->id && $this->access->canViewLesson($user, $course, $lesson->id);
        $video = $lesson->revision?->video_asset_id ? VideoAsset::find($lesson->revision->video_asset_id) : null;

        return $this->grantAsset($user, $course, $video, $ok, $lesson->id, $kind);
    }

    /** Canlı görüş yazıları: yalnız ödənişli tələbələr (demo yox), eyni token qoruması. */
    public function grantRecording(User $user, Course $course, ?VideoAsset $video, string $kind = 'issue'): ?array
    {
        return $this->grantAsset($user, $course, $video, $this->access->canSeePaidFeatures($user, $course), null, $kind);
    }

    private function grantAsset(User $user, Course $course, ?VideoAsset $video, bool $ok, ?int $lessonId, string $kind): ?array
    {
        $lesson = (object) ['id' => $lessonId];
        $deadline = $ok ? $this->access->accessDeadline($user, $course) : null;
        $now = CarbonImmutable::now();
        $expires = $deadline ? CarbonImmutable::instance(min($now->addMinutes(config('platform.video.token_ttl_minutes')), $deadline)) : null;

        if (! $ok || ! $expires || $expires->lte($now) || ! $video || $video->status !== 'ready') {
            $this->log($user, $lesson, 'denied');

            return null;
        }
        $this->log($user, $lesson, $kind);
        $this->detectAnomaly($user);
        $ttl = $expires->getTimestamp() - $now->getTimestamp();

        return [
            'src' => $this->video->playbackUrl($video, $expires),
            'expires_at' => $expires->toIso8601String(),
            // Player bitməyə ~60 saniyə qalmış yenidən soruşur
            'refresh_in' => max(15, $ttl - 60),
        ];
    }

    private function log(User $user, object $lesson, string $kind): void
    {
        if (! $lesson->id) {
            return; // yazılar dərsə bağlı deyil; anomaliya sayğacı dərs tokenləri üzrə aparılır
        }
        DB::table('video_token_log')->insert(['user_id' => $user->id, 'lesson_id' => $lesson->id,
            'ip_hash' => Ip::hash(request()->ip()), 'kind' => $kind, 'created_at' => now()]);
    }

    private function detectAnomaly(User $user): void
    {
        $limit = (int) config('platform.video.anomaly_tokens_per_hour');
        $n = DB::table('video_token_log')->where('user_id', $user->id)->where('kind', 'issue')->where('created_at', '>', now()->subHour())->count();
        $ips = DB::table('video_token_log')->where('user_id', $user->id)->where('created_at', '>', now()->subHour())->distinct()->count('ip_hash');
        if ($n === $limit + 1 || $ips === 4) {
            Log::warning('unusual video viewing volume', ['user_id' => $user->id, 'tokens_last_hour' => $n, 'distinct_ips' => $ips]);
            Audit::record('video.anomaly', $user, after: ['tokens_last_hour' => $n, 'distinct_ips' => $ips], actorId: $user->id);
        }
    }
}

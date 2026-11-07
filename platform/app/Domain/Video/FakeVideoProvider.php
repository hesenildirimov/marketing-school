<?php

namespace App\Domain\Video;

use App\Models\VideoAsset;

/** Test və lokal inkişaf üçün. Bunny ilə eyni URL formatını istehsal edir (imza testləri üçün). */
final class FakeVideoProvider implements VideoProvider
{
    public static array $deleted = [];

    public function name(): string
    {
        return 'fake';
    }

    public function createUpload(string $title): UploadTicket
    {
        $id = 'fake-'.bin2hex(random_bytes(6));

        return new UploadTicket($id, url('/platform/_fake-tus'), ['VideoId' => $id]);
    }

    public function playbackUrl(VideoAsset $video, \DateTimeInterface $expiresAt): string
    {
        return BunnyStreamProvider::signDirectoryUrl((string) config('services.bunny.cdn_host', 'cdn.test'), '/'.$video->provider_video_id.'/playlist.m3u8',
            '/'.$video->provider_video_id.'/', (string) config('services.bunny.token_key', 'k'), $expiresAt->getTimestamp());
    }

    public function status(VideoAsset $video): array
    {
        return ['status' => 'ready', 'duration' => $video->duration_seconds ?? 600];
    }

    public function delete(VideoAsset $video): void
    {
        self::$deleted[] = $video->provider_video_id;
    }
}

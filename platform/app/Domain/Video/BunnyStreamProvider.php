<?php

namespace App\Domain\Video;

use App\Models\VideoAsset;
use Illuminate\Support\Facades\Http;

/**
 * Bunny Stream. Açarlar yalnız server .env-indədir.
 * - Yükləmə: TUS (https://video.bunnycdn.com/tusupload), imza = sha256(library_id + api_key + expire + video_id).
 * - Playback: CDN Token Authentication, qovluq (path) tokeni — playlist və bütün seqmentlər eyni token ilə imzalanır.
 *   Pull Zone-da "Token Authentication" aktiv və "Allowed referrers" = marketingschool.az olmalıdır.
 * DİQQƏT: format Bunny-nin rəsmi PHP nümunəsinə əsaslanır; real kitabxanada staging-də yoxlanmalıdır.
 */
final class BunnyStreamProvider implements VideoProvider
{
    public function __construct(private array $cfg) {}

    public function name(): string
    {
        return 'bunny';
    }

    public function createUpload(string $title): UploadTicket
    {
        $res = Http::withHeaders(['AccessKey' => $this->cfg['api_key']])->acceptJson()->timeout(15)
            ->post("https://video.bunnycdn.com/library/{$this->cfg['library_id']}/videos", ['title' => $title])->throw()->json();
        $videoId = $res['guid'];
        $expire = time() + 3600;
        $signature = hash('sha256', $this->cfg['library_id'].$this->cfg['api_key'].$expire.$videoId);

        return new UploadTicket($videoId, 'https://video.bunnycdn.com/tusupload', [
            'AuthorizationSignature' => $signature, 'AuthorizationExpire' => (string) $expire,
            'VideoId' => $videoId, 'LibraryId' => (string) $this->cfg['library_id'],
        ]);
    }

    public function playbackUrl(VideoAsset $video, \DateTimeInterface $expiresAt): string
    {
        return self::signDirectoryUrl($this->cfg['cdn_host'], '/'.$video->provider_video_id.'/playlist.m3u8',
            '/'.$video->provider_video_id.'/', $this->cfg['token_key'], $expiresAt->getTimestamp());
    }

    public static function signDirectoryUrl(string $host, string $path, string $allowedPath, string $key, int $expires): string
    {
        $params = 'token_path='.$allowedPath;
        $token = rtrim(strtr(base64_encode(hash('sha256', $key.$allowedPath.$expires.$params, true)), '+/', '-_'), '=');

        return "https://{$host}/bcdn_token={$token}&expires={$expires}&token_path=".rawurlencode($allowedPath).$path;
    }

    public function status(VideoAsset $video): array
    {
        $res = Http::withHeaders(['AccessKey' => $this->cfg['api_key']])->acceptJson()->timeout(15)
            ->get("https://video.bunnycdn.com/library/{$this->cfg['library_id']}/videos/{$video->provider_video_id}")->throw()->json();
        // Bunny status: 0 created, 1 uploaded, 2 processing, 3 transcoding, 4 finished, 5 error, 6 upload failed
        $status = match ((int) ($res['status'] ?? 0)) {
            4 => 'ready', 5, 6 => 'failed', 0 => 'uploading', default => 'processing'
        };

        return ['status' => $status, 'duration' => isset($res['length']) ? (int) $res['length'] : null];
    }

    public function delete(VideoAsset $video): void
    {
        Http::withHeaders(['AccessKey' => $this->cfg['api_key']])->timeout(15)
            ->delete("https://video.bunnycdn.com/library/{$this->cfg['library_id']}/videos/{$video->provider_video_id}")->throw();
    }
}

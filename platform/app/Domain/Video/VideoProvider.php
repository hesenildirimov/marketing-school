<?php

namespace App\Domain\Video;

use App\Models\VideoAsset;

/**
 * Video adapter qatı (bölmə 4.3): provayderə bağlı kod yalnız implementasiyalardadır.
 */
interface VideoProvider
{
    public function name(): string;

    /** Provayderdə video yaradır və brauzerdən birbaşa (TUS) yükləmə üçün imzalı bilet qaytarır. */
    public function createUpload(string $title): UploadTicket;

    /** İmzalı, qısa ömürlü HLS URL-i (yalnız icazə yoxlanandan sonra çağırılır). */
    public function playbackUrl(VideoAsset $video, \DateTimeInterface $expiresAt): string;

    /** @return array{status: string, duration: int|null} */
    public function status(VideoAsset $video): array;

    public function delete(VideoAsset $video): void;
}

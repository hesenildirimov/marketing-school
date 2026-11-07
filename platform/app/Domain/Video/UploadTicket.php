<?php

namespace App\Domain\Video;

final class UploadTicket
{
    public function __construct(
        public readonly string $providerVideoId,
        public readonly string $endpoint,
        public readonly array $headers,
    ) {}
}

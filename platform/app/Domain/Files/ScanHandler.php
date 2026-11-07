<?php

namespace App\Domain\Files;

/** Outbox handler: `file.scan` */
final class ScanHandler
{
    public function __construct(private FileService $files) {}

    public function handle(array $payload): void
    {
        $this->files->scan($payload['file_id']);
    }
}

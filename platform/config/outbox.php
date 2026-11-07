<?php

use App\Domain\Certificates\EvaluateHandler;
use App\Domain\Files\ScanHandler;
use App\Domain\Mentoring\UnlockedHandler;

// Outbox hadisə tipi => handler sinfi (handle(array $payload): void)
return [
    'handlers' => [
        'file.scan' => ScanHandler::class,
        'enrollment.modules_completed' => UnlockedHandler::class,
        'diploma.evaluate' => EvaluateHandler::class,
    ],
];

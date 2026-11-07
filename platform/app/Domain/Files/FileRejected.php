<?php

namespace App\Domain\Files;

final class FileRejected extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}

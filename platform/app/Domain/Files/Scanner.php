<?php

namespace App\Domain\Files;

interface Scanner
{
    /** @return array{status: 'clean'|'infected'|'error', result: string} */
    public function scan(string $path): array;
}

<?php

return [
    'driver' => 'argon2id',
    'bcrypt' => ['rounds' => env('BCRYPT_ROUNDS', 12), 'verify' => true],
    // OWASP Password Storage Cheat Sheet: m=19 MiB, t=2, p=1 minimum. Biz m=64 MiB, t=3 istifadə edirik.
    'argon' => [
        'memory' => (int) env('ARGON_MEMORY', 65536),
        'threads' => 1,
        'time' => (int) env('ARGON_TIME', 3),
        'verify' => true,
    ],
    'rehash_on_login' => true,
];

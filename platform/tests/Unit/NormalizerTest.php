<?php

namespace Tests\Unit;

use App\Support\Normalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NormalizerTest extends TestCase
{
    #[DataProvider('emails')]
    public function test_email_normalization(string $in, string $out): void
    {
        $this->assertSame($out, Normalizer::email($in));
    }

    public static function emails(): array
    {
        return [
            ['Ali.Veli+demo@Gmail.com', 'aliveli@gmail.com'],
            ['  a.l.i@googlemail.com ', 'ali@gmail.com'],
            ['ALI@GMAIL.COM', 'ali@gmail.com'],
            ['ali+x+y@mail.az', 'ali@mail.az'],
            ['ali.veli@mail.az', 'ali.veli@mail.az'], // nöqtələr yalnız Gmail-də silinir
        ];
    }

    #[DataProvider('phones')]
    public function test_phone_normalization(string $in, ?string $out): void
    {
        $this->assertSame($out, Normalizer::phone($in));
    }

    public static function phones(): array
    {
        return [
            ['050 123 45 67', '+994501234567'],
            ['0 50 123 45 67', '+994501234567'],
            ['+994 50 123 45 67', '+994501234567'],
            ['994501234567', '+994501234567'],
            ['(050) 123-45-67', '+994501234567'],
            ['+994-55-765-43-21', '+994557654321'],
            ['12345', null],
            ['abc', null],
        ];
    }
}

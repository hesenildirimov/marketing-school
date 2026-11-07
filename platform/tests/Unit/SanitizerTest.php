<?php

namespace Tests\Unit;

use App\Domain\Assessment\Sanitizer;
use Tests\TestCase;

/** Tapşırıq cavablarının XSS-ə qarşı sanitizasiyası (bölmə 6). */
class SanitizerTest extends TestCase
{
    public function test_xss_vectors_are_neutralised(): void
    {
        $vectors = [
            '<script>alert(1)</script>', '<img src=x onerror=alert(1)>', '<a href="javascript:alert(1)">x</a>', '<svg onload=alert(1)>',
            '<iframe src="https://evil"></iframe>', '<p style="background:url(javascript:alert(1))">x</p>', '<a href="data:text/html,<script>alert(1)</script>">d</a>',
            '"><script>alert(1)</script>', '<details open ontoggle=alert(1)>', '<form action="https://evil"><button>x</button></form>',
        ];
        foreach ($vectors as $v) {
            $out = Sanitizer::html($v);
            $this->assertDoesNotMatchRegularExpression('/<script|onerror|onload|ontoggle|javascript:|<iframe|<svg|<form|style=|data:text/i', $out, $v);
        }
        $this->assertSame("<p>Salam<br />\ndünya</p><p>İkinci abzas</p>", Sanitizer::html("Salam\ndünya\n\nİkinci abzas"));
        $this->assertStringContainsString('rel="nofollow', Sanitizer::html('<a href="https://ok.example">link</a>'));
    }
}

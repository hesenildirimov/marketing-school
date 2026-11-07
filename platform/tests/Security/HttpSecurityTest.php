<?php

namespace Tests\Security;

use App\Http\Middleware\VerifyProxySignature;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

class HttpSecurityTest extends TestCase
{
    private function jws(array $claims, string $secret = 'test-proxy-secret'): string
    {
        $b = fn ($v) => rtrim(strtr(base64_encode(json_encode($v)), '+/', '-_'), '=');
        $h = $b(['alg' => 'HS256', 'typ' => 'JWT']);
        $p = $b($claims);
        $s = rtrim(strtr(base64_encode(hash_hmac('sha256', "$h.$p", $secret, true)), '+/', '-_'), '=');

        return "$h.$p.$s";
    }

    public function test_origin_rejects_requests_without_valid_netlify_signature(): void
    {
        config(['platform.proxy.require_signature' => true]);
        $this->get('/platform/login')->assertStatus(403);
        $this->get('/platform/login', ['x-nf-sign' => $this->jws(['iss' => 'netlify'], 'wrong')])->assertStatus(403);
        $this->get('/platform/login', ['x-nf-sign' => $this->jws(['iss' => 'other'])])->assertStatus(403);
        $this->get('/platform/login', ['x-nf-sign' => $this->jws(['iss' => 'netlify', 'exp' => time() - 5])])->assertStatus(403);
        $this->get('/platform/login', ['x-nf-sign' => $this->jws(['iss' => 'netlify']), 'x-nf-client-connection-ip' => '5.6.7.8'])->assertOk();
        $this->get('/platform/up')->assertOk();
    }

    public function test_alg_none_rejected(): void
    {
        $b = fn ($v) => rtrim(strtr(base64_encode(json_encode($v)), '+/', '-_'), '=');
        $this->assertFalse(VerifyProxySignature::valid($b(['alg' => 'none']).'.'.$b(['iss' => 'netlify']).'.', 'test-proxy-secret'));
    }

    public function test_security_headers_present(): void
    {
        $r = $this->get('/platform/login')->assertOk();
        $csp = $r->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringContainsString('max-age=31536000', $r->headers->get('Strict-Transport-Security'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $r->headers->get('Referrer-Policy'));
        $this->assertNotEmpty($r->headers->get('Permissions-Policy'));
        $this->assertStringContainsString('noindex', $r->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $r->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_csrf_blocks_cross_site_post_without_token(): void
    {
        $mw = new class(app(), app('encrypter')) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false; // test mühitində də real yoxlama
            }
        };
        $make = function (array $headers, array $data = []) {
            $r = Request::create('/platform/login', 'POST', $data, [], [], $headers);
            $r->setLaravelSession(app('session.store'));

            return $r;
        };
        $ok = fn () => response('ok');
        app('session.store')->regenerateToken();
        try {
            $mw->handle($make(['HTTP_SEC_FETCH_SITE' => 'cross-site']), $ok);
            $this->fail('cross-site POST bloklanmalıdır');
        } catch (TokenMismatchException) {
        }
        try {
            $mw->handle($make([]), $ok);
            $this->fail('tokensiz POST bloklanmalıdır');
        } catch (TokenMismatchException) {
        }
        $this->assertSame('ok', $mw->handle($make(['HTTP_SEC_FETCH_SITE' => 'same-origin']), $ok)->getContent());
        $this->assertSame('ok', $mw->handle($make([], ['_token' => app('session.store')->token()]), $ok)->getContent());
    }

    public function test_verify_link_page_sends_no_referrer(): void
    {
        $this->get('/platform/verify?t=abc')->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_locale_switch_and_three_languages(): void
    {
        $this->get('/platform/login?lang=en')->assertSee('Log in to the platform')->assertSee('<html lang="en">', false);
        $this->get('/platform/login?lang=ru')->assertSee('Вход на платформу');
        $this->get('/platform/login?lang=az')->assertSee('Platformaya daxil olun');
        $this->get('/platform/login?lang=xx')->assertSee('<html lang="az">', false);
    }

    public function test_locale_saved_on_account(): void
    {
        $u = $this->makeUser();
        $this->signIn($u)->get('/platform/dashboard?lang=ru')->assertOk();
        $this->assertSame('ru', $u->fresh()->locale);
    }
}

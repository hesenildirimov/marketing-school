<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Origin yalnız Netlify-ın imzalı proxy-sindən gələn sorğuları qəbul edir (x-nf-sign: HS256 JWS).
 * İmza etibarlıdırsa müştəri IP-si Netlify başlığından götürülür (rate limit üçün); əks halda 403.
 */
class VerifyProxySignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('platform/up')) {
            return $next($request);
        }
        if (! config('platform.proxy.require_signature')) {
            return $next($request);
        }
        $secret = (string) config('platform.proxy.secret');
        $jws = (string) $request->headers->get('x-nf-sign');
        if ($secret === '' || ! self::valid($jws, $secret)) {
            abort(403);
        }
        $client = $request->headers->get('x-nf-client-connection-ip');
        if ($client && filter_var($client, FILTER_VALIDATE_IP)) {
            $request->server->set('REMOTE_ADDR', $client);
        }

        return $next($request);
    }

    public static function valid(string $jws, string $secret): bool
    {
        $parts = explode('.', $jws);
        if (count($parts) !== 3) {
            return false;
        }
        [$h, $p, $s] = $parts;
        $header = json_decode(self::b64d($h), true);
        if (($header['alg'] ?? null) !== 'HS256') {
            return false;
        }
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$h}.{$p}", $secret, true)), '+/', '-_'), '=');
        if (! hash_equals($expected, $s)) {
            return false;
        }
        $claims = json_decode(self::b64d($p), true);
        if (($claims['iss'] ?? null) !== 'netlify') {
            return false;
        }
        if (isset($claims['exp']) && $claims['exp'] < time()) {
            return false;
        }

        return true;
    }

    private static function b64d(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'), true);
    }
}

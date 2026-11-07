<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Netlify proxy cavablarına netlify.toml başlıqları tətbiq olunmur, ona görə hamısı origin-də qurulur. */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        app()->instance('csp-nonce', $nonce);
        $response = $next($request);

        $video = config('services.bunny.cdn_host') ? 'https://'.config('services.bunny.cdn_host') : '';
        $pay = implode(' ', array_filter((array) config('services.payment.form_action_hosts')));
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self'",
            "img-src 'self' data: {$video}",
            "media-src 'self' blob: {$video}",
            "connect-src 'self' {$video} https://video.bunnycdn.com",
            "font-src 'self'",
            "worker-src 'self' blob:",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'none'",
            "form-action 'self' {$pay}",
            "frame-ancestors 'none'",
            'upgrade-insecure-requests',
        ]);
        $h = $response->headers;
        $h->set('Content-Security-Policy', $csp);
        $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        if (! $h->has('Referrer-Policy')) {
            $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        $h->set('X-Robots-Tag', 'noindex, nofollow');
        if (! $h->has('Cache-Control') || ! str_contains((string) $h->get('Cache-Control'), 'public')) {
            $h->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}

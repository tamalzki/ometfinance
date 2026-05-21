<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies defensive HTTP response headers.
 *
 * HSTS is only sent over an HTTPS request — browsers ignore it on HTTP,
 * and we don't want a misconfigured dev box accidentally pinning the host.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Disable MIME-type sniffing — prevents some XSS via content confusion.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Block this app from being framed by other origins (clickjacking).
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Legacy XSS auditor toggle — modern browsers ignore it, but it still
        // hardens older IE/Safari versions and satisfies common audit checks.
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Restrict the Referer header sent to other origins.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Strip default access to sensitive browser features the app doesn't use.
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()'
        );

        // HSTS — only meaningful when served over HTTPS.
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}

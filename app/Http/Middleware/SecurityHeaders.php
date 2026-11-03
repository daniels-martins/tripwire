<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers the panel-managed Nginx config does not set.
 *
 * Nginx already sends X-Frame-Options and X-Content-Type-Options, and that
 * file is rewritten on every deploy, so the rest are set here where they
 * survive. No Content-Security-Policy yet: the pages run inline Alpine and
 * chart bootstrapping, and a policy strict enough to be worth having would
 * need nonces threaded through all of it.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Referrers leak the corridor and amount someone was looking at.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $response->headers->set('X-Content-Type-Options', 'nosniff', false);

        // Only over TLS, and not for the whole zone: this host is served
        // through Cloudflare, the siblings are other people's sites.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}

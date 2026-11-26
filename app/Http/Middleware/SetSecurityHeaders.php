<?php

namespace App\Http\Middleware;

use App\Configs\SecurityConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    public function __construct(private readonly SecurityConfig $security) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', $this->security->permissionsPolicy());
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $policy = [];
        foreach ($this->security->contentSecurityPolicy() as $directive => $sources) {
            $policy[] = $directive.' '.implode(' ', $sources);
        }
        $response->headers->set('Content-Security-Policy', implode('; ', $policy));

        $hsts = $request->isSecure() ? $this->security->hstsHeader() : null;
        if ($hsts !== null) {
            $response->headers->set('Strict-Transport-Security', $hsts);
        }

        return $response;
    }
}

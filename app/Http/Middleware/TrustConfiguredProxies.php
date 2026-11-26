<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class TrustConfiguredProxies extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $proxies = config('app.trusted_proxies', []);
        if (is_array($proxies) && array_intersect($proxies, ['*', '**']) !== []) {
            throw new InvalidArgumentException('Trusted proxies must be configured explicitly; wildcard proxy trust is not allowed.');
        }

        $this->proxies = is_array($proxies) && $proxies !== [] ? $proxies : null;

        return parent::handle($request, $next);
    }
}

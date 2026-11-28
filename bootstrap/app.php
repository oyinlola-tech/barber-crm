<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireFirstLoginPasswordChange;
use App\Http\Middleware\RequirePrivilegedMfa;
use App\Http\Middleware\RequireVerifiedEmail;
use App\Http\Middleware\ResolveBarbershop;
use App\Http\Middleware\SetSecurityHeaders;
use App\Http\Middleware\TrustConfiguredProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(TrustProxies::class, TrustConfiguredProxies::class);
        $middleware->append(SetSecurityHeaders::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            RequireVerifiedEmail::class,
            RequireFirstLoginPasswordChange::class,
            RequirePrivilegedMfa::class,
        ]);
        $middleware->validateCsrfTokens(except: ['webhooks/whatsapp']);
        $middleware->alias([
            'business' => ResolveBarbershop::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

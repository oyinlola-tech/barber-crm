<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class RequireVerifiedEmail
{
    /** @var list<string> */
    private const VERIFICATION_PATHS = [
        'email/verify',
        'email/verify/*',
        'email/verification-notification',
        'first-login/password',
        'first-login/password/*',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null
            || $user->hasVerifiedEmail()
            || $request->is(...self::VERIFICATION_PATHS)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Your email address is not verified.');
        }

        return Redirect::guest(URL::route('verification.notice'));
    }
}

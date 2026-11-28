<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireFirstLoginPasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->requiresPasswordChange()) {
            return $next($request);
        }

        if ($user->temporaryPasswordExpired()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your temporary password expired. Request a password reset to continue.',
                ], 423);
            }

            return redirect()->route('login')->with('status', 'Your temporary password expired. Request a password reset to continue.');
        }

        if ($request->is(
            'first-login/password',
            'first-login/password/*',
            'email/verify',
            'email/verify/*',
            'email/verification-notification',
            'logout',
        )) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Change your temporary password before continuing.',
            ], 423);
        }

        return redirect()->route('account.first-login-password');
    }
}

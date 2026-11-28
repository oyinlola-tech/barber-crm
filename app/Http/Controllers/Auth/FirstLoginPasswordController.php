<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\Commands\UpdateAccountPasswordCommand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class FirstLoginPasswordController extends Controller
{
    public function show(Request $request): Response
    {
        abort_unless($request->user()->requiresPasswordChange(), 404);

        $expiresAt = $request->user()->temporary_password_expires_at;

        return Inertia::render('auth/first-login-password', [
            'expiresAt' => $expiresAt === null ? null : Carbon::parse($expiresAt)->toIso8601String(),
        ]);
    }

    public function update(Request $request, UpdateAccountPasswordCommand $command): RedirectResponse
    {
        abort_unless($request->user()->requiresPasswordChange(), 404);

        $attributes = $request->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', Password::min(12)->uncompromised(), 'confirmed'],
        ]);

        $command->execute(
            (int) $request->user()->getKey(),
            $attributes['password'],
            $request->session()->getId(),
        );

        return to_route('dashboard')->with('success', 'Your password has been updated.');
    }
}

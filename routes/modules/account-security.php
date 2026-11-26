<?php

use App\Http\Controllers\Auth\AccountSecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'business'])->group(function (): void {
    Route::get('/settings/security', [AccountSecurityController::class, 'show'])
        ->name('account.security');
    Route::post('/settings/security/confirm-password', [AccountSecurityController::class, 'confirmPassword'])
        ->middleware('throttle:5,1')
        ->name('account.security.confirm-password');
    Route::delete('/settings/security/sessions/{sessionId}', [AccountSecurityController::class, 'revokeSession'])
        ->where('sessionId', '[A-Za-z0-9]+')
        ->middleware('throttle:10,1')
        ->name('account.security.sessions.revoke');
    Route::delete('/settings/security/sessions', [AccountSecurityController::class, 'revokeOtherSessions'])
        ->middleware('throttle:5,1')
        ->name('account.security.sessions.revoke-others');
});

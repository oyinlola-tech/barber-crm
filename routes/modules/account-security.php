<?php

use App\Http\Controllers\Auth\AccountSecurityController;
use App\Http\Controllers\Auth\FirstLoginPasswordController;
use App\Http\Controllers\Notifications\WebPushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'business'])->group(function (): void {
    Route::get('/first-login/password', [FirstLoginPasswordController::class, 'show'])
        ->withoutMiddleware('business')
        ->name('account.first-login-password');
    Route::put('/first-login/password', [FirstLoginPasswordController::class, 'update'])
        ->withoutMiddleware('business')
        ->middleware('throttle:5,1')
        ->name('account.first-login-password.update');
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
    Route::post('/settings/security/web-push-subscriptions', [WebPushSubscriptionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('account.security.web-push.store');
    Route::delete('/settings/security/web-push-subscriptions', [WebPushSubscriptionController::class, 'destroy'])
        ->middleware('throttle:10,1')
        ->name('account.security.web-push.destroy');
});

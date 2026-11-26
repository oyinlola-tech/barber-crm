<?php

use App\Http\Controllers\Businesses\BusinessSettingsController;
use App\Http\Controllers\Businesses\MessagingSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'business'])->group(function (): void {
    Route::get('/settings/business', [BusinessSettingsController::class, 'show'])->name('settings.business');
    Route::put('/settings/business', [BusinessSettingsController::class, 'update'])->name('settings.business.update');
    Route::put('/settings/messaging', [MessagingSettingsController::class, 'update'])->name('settings.messaging.update');
});

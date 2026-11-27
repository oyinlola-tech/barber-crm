<?php

use App\Http\Controllers\Analytics\AnalyticsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'business'])->group(function (): void {
    Route::get('/analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
});

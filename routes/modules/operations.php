<?php

use App\Controllers\Operations\AuditLogController;
use App\Controllers\Operations\OperationalHealthController;
use Illuminate\Support\Facades\Route;

Route::get('/ready', [OperationalHealthController::class, 'ready'])->name('health.ready');

Route::middleware(['auth', 'business'])->group(function (): void {
    Route::get('/operations/health', [OperationalHealthController::class, 'show'])->name('operations.health');
    Route::get('/operations/audit', [AuditLogController::class, 'index'])->name('operations.audit');
});

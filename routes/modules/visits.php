<?php

use App\Http\Controllers\Visits\VisitController;
use App\Models\Visit;
use Illuminate\Support\Facades\Route;

Route::bind('visit', static fn (string $value): Visit => Visit::withTrashed()->findOrFail($value));

Route::middleware(['auth', 'business'])->group(function (): void {
    Route::post('/visits', [VisitController::class, 'store'])->name('visits.store');
    Route::put('/visits/{visit}', [VisitController::class, 'update'])->name('visits.update');
    Route::delete('/visits/{visit}', [VisitController::class, 'destroy'])->name('visits.destroy');
    Route::post('/visits/{visit}/restore', [VisitController::class, 'restore'])->name('visits.restore');
});

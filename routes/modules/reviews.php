<?php

use App\Http\Controllers\Reviews\ReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'business'])->group(function (): void {
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::get('/reviews/export', [ReviewController::class, 'export'])->name('reviews.export');
    Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
});

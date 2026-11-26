<?php

use App\Http\Controllers\Services\ServiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'business'])->group(function (): void {
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
    Route::put('/services/{service}', [ServiceController::class, 'update'])->whereNumber('service')->name('services.update');
    Route::post('/service-categories', [ServiceController::class, 'storeCategory'])->name('service-categories.store');
    Route::put('/service-categories/{category}', [ServiceController::class, 'updateCategory'])->whereNumber('category')->name('service-categories.update');
    Route::delete('/service-categories/{category}', [ServiceController::class, 'destroyCategory'])->whereNumber('category')->name('service-categories.destroy');
});

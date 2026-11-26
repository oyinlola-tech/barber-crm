<?php

use App\Http\Controllers\Customers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'business'])->group(function (): void {
    Route::get('/customers/export', [CustomerController::class, 'export'])->name('customers.export');
    Route::post('/customers/import', [CustomerController::class, 'import'])->name('customers.import');
    Route::resource('customers', CustomerController::class)->only(['index', 'show', 'store', 'update']);
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    Route::post('/customers/{customer}/anonymize', [CustomerController::class, 'anonymize'])->name('customers.anonymize');
    Route::delete('/customers/{customer}/permanent', [CustomerController::class, 'permanentlyDelete'])->name('customers.permanent-delete');
    Route::post('/customers/{customer}/restore', [CustomerController::class, 'restore'])->name('customers.restore');
    Route::post('/customers/{customer}/notes', [CustomerController::class, 'addNote'])->name('customers.notes.store');
    Route::put('/customers/{customer}/tags', [CustomerController::class, 'syncTags'])->name('customers.tags.sync');
});

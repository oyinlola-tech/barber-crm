<?php

use App\Http\Controllers\Customers\CustomerConsentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'business'])
    ->post('/customers/{customer}/consents', [CustomerConsentController::class, 'store'])
    ->name('customers.consents.store');

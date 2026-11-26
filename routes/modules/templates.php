<?php

use App\Http\Controllers\Templates\MessageTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'business'])->group(function (): void {
    Route::get('/templates', [MessageTemplateController::class, 'index'])->name('templates.index');
    Route::post('/templates', [MessageTemplateController::class, 'store'])->name('templates.store');
    Route::put('/templates/{template}', [MessageTemplateController::class, 'update'])->name('templates.update');
    Route::post('/templates/{template}/submit', [MessageTemplateController::class, 'submit'])->name('templates.submit');
    Route::post('/templates/synchronize', [MessageTemplateController::class, 'synchronize'])->name('templates.synchronize');
    Route::patch('/templates/{template}/enabled', [MessageTemplateController::class, 'enabled'])->name('templates.enabled');
    Route::patch('/templates/{template}/variables', [MessageTemplateController::class, 'variables'])->name('templates.variables');
});

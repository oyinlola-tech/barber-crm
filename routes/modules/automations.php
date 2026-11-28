<?php

use App\Http\Controllers\Automations\AutomationRuleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'business'])->group(function (): void {
    Route::get('/automations', [AutomationRuleController::class, 'index'])->name('automations.index');
    Route::post('/automations', [AutomationRuleController::class, 'store'])->name('automations.store');
    Route::patch('/automations/{automationRule}', [AutomationRuleController::class, 'update'])->name('automations.update');
    Route::delete('/automations/{automationRule}', [AutomationRuleController::class, 'destroy'])->name('automations.destroy');
});

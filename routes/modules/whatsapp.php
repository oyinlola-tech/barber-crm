<?php

use App\Http\Controllers\WhatsApp\WhatsAppConnectionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'business'])->group(function (): void {
    Route::get('/settings/whatsapp', [WhatsAppConnectionController::class, 'show'])->name('whatsapp.connection.show');
    Route::put('/settings/whatsapp', [WhatsAppConnectionController::class, 'store'])->name('whatsapp.connection.store');
    Route::delete('/settings/whatsapp', [WhatsAppConnectionController::class, 'destroy'])->name('whatsapp.connection.destroy');
});

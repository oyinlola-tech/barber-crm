<?php

use App\Http\Controllers\Messaging\ScheduledMessageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'business'])->group(function (): void {
    Route::get('/messages/scheduled', [ScheduledMessageController::class, 'index'])->name('messages.scheduled.index');
    Route::post('/messages/scheduled/{scheduledMessage}/cancel', [ScheduledMessageController::class, 'cancel'])->name('messages.scheduled.cancel');
    Route::post('/messages/scheduled/{scheduledMessage}/retry', [ScheduledMessageController::class, 'retry'])->name('messages.scheduled.retry');
});

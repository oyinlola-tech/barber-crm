<?php

use App\Http\Controllers\Conversations\ConversationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'business'])->group(function (): void {
    Route::get('/inbox', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/inbox/{conversation}', [ConversationController::class, 'show'])->whereNumber('conversation')->name('conversations.show');
    Route::post('/inbox/{conversation}/reply', [ConversationController::class, 'reply'])->whereNumber('conversation')->name('conversations.reply');
    Route::post('/inbox/{conversation}/template-reply', [ConversationController::class, 'templateReply'])->middleware('throttle:30,1')->whereNumber('conversation')->name('conversations.template-reply');
    Route::post('/inbox/{conversation}/assignment', [ConversationController::class, 'assign'])->whereNumber('conversation')->name('conversations.assign');
    Route::post('/inbox/{conversation}/notes', [ConversationController::class, 'addNote'])->whereNumber('conversation')->name('conversations.notes.store');
    Route::patch('/inbox/{conversation}/status', [ConversationController::class, 'setStatus'])->whereNumber('conversation')->name('conversations.status');
    Route::post('/inbox/{conversation}/read', [ConversationController::class, 'markRead'])->whereNumber('conversation')->name('conversations.read');
});

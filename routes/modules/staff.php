<?php

use App\Http\Controllers\Staff\RoleController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Staff\StaffInvitationController;
use Illuminate\Support\Facades\Route;

Route::get('/staff/invitations', [StaffInvitationController::class, 'show'])->name('staff.invitations.show');
Route::post('/staff/invitations/accept', [StaffInvitationController::class, 'accept'])->middleware('throttle:6,1')->name('staff.invitations.accept');

Route::middleware(['auth', 'business'])->group(function (): void {
    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff/invitations', [StaffController::class, 'invite'])->name('staff.invite');
    Route::delete('/staff/invitations/{invitation}', [StaffController::class, 'revokeInvitation'])->whereNumber('invitation')->name('staff.invitations.revoke');
    Route::patch('/staff/{staff}/status', [StaffController::class, 'setStatus'])->whereNumber('staff')->name('staff.status');
    Route::patch('/staff/{staff}/profile', [StaffController::class, 'updateProfile'])->whereNumber('staff')->name('staff.profile');
    Route::patch('/staff/{staff}/role', [StaffController::class, 'assignRole'])->whereNumber('staff')->name('staff.role');
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->whereNumber('role')->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->whereNumber('role')->name('roles.destroy');
});

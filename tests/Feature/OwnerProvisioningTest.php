<?php

use App\Mail\OwnerTemporaryAccess;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('provisions the first owner without exposing a password argument', function (): void {
    Notification::fake();
    Mail::fake();
    config(['mail.default' => 'smtp']);
    $this->artisan('app:provision-owner', [
        '--name' => 'Barber Owner',
        '--email' => 'owner@example.test',
        '--business' => 'Corner Barber',
    ])
        ->expectsOutputToContain('Owner account provisioned')
        ->assertSuccessful();

    $owner = User::query()->firstOrFail();
    $temporaryPassword = null;
    Mail::assertSent(OwnerTemporaryAccess::class, function (OwnerTemporaryAccess $mail) use (&$temporaryPassword): bool {
        $temporaryPassword = $mail->temporaryPassword;

        return true;
    });

    expect(User::query()->count())->toBe(1)
        ->and(Business::query()->firstOrFail()->name)->toBe('Corner Barber')
        ->and($owner->businesses()->firstOrFail()->pivot->role)->toBe('owner')
        ->and($owner->hasVerifiedEmail())->toBeFalse()
        ->and($owner->requiresPasswordChange())->toBeTrue()
        ->and($owner->temporaryPasswordExpired())->toBeFalse()
        ->and($temporaryPassword)->toBeString()
        ->and(Hash::check($temporaryPassword, $owner->password))->toBeTrue();

    Notification::assertSentTo($owner, VerifyEmail::class);
});

it('does not provision an owner when no private mail transport is configured', function (): void {
    config(['mail.default' => 'log']);

    $this->artisan('app:provision-owner', [
        '--name' => 'Barber Owner',
        '--email' => 'owner@example.test',
        '--business' => 'Corner Barber',
    ])->expectsOutputToContain('Configure a private email delivery service')
        ->assertFailed();

    expect(User::query()->exists())->toBeFalse()
        ->and(Business::query()->exists())->toBeFalse();
});

it('does not expose public account registration', function (): void {
    $this->get('/register')->assertNotFound();
});

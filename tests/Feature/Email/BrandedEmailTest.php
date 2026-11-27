<?php

use App\Models\User;
use App\Notifications\BrandedPasswordResetNotification;
use App\Notifications\CustomerMessageReceivedNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders password reset and verification messages with the shared brand footer', function (): void {
    config(['app.url' => 'https://crm.example.test', 'app.name' => 'Barber CRM']);
    $user = User::factory()->create();

    $resetHtml = (new BrandedPasswordResetNotification('one-time-reset-token'))
        ->toMail($user)
        ->render();
    $verificationHtml = (new VerifyEmail)->toMail($user)->render();

    expect($resetHtml)
        ->toContain('Choose a new password.')
        ->toContain('one-time-reset-token')
        ->toContain('Good service stays with you')
        ->and($verificationHtml)
        ->toContain('Confirm your email address.')
        ->toContain('Confirm email')
        ->toContain('Good service stays with you');
});

it('renders inbound operational email without including customer content', function (): void {
    config(['app.url' => 'https://crm.example.test', 'app.name' => 'Barber CRM']);
    $user = User::factory()->create();
    $html = (new CustomerMessageReceivedNotification('notification-id', 41))
        ->toMail($user)
        ->render();

    expect($html)
        ->toContain('https://crm.example.test/inbox/41')
        ->toContain('Customer message content is kept out of email')
        ->toContain('Good service stays with you');
});

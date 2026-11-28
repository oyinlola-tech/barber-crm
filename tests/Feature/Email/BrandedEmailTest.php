<?php

use App\Models\User;
use App\Notifications\BrandedPasswordResetNotification;
use App\Notifications\CustomerMessageReceivedNotification;
use App\Notifications\MessageDeliveryUncertainNotification;
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
        ->toContain('GOOD SERVICE STAYS WITH PEOPLE')
        ->toContain('deserves a return visit')
        ->and($verificationHtml)
        ->toContain('Confirm your email address.')
        ->toContain('Confirm email')
        ->toContain('GOOD SERVICE STAYS WITH PEOPLE')
        ->toContain('deserves a return visit');
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
        ->toContain('GOOD SERVICE STAYS WITH PEOPLE')
        ->toContain('deserves a return visit');
});

it('renders uncertain WhatsApp delivery alerts with the shared branded footer', function (): void {
    config(['app.url' => 'https://crm.example.test', 'app.name' => 'Barber CRM']);
    $user = User::factory()->create();
    $html = (new MessageDeliveryUncertainNotification('notification-id', '/messages/scheduled'))
        ->toMail($user)
        ->render();

    expect($html)
        ->toContain('https://crm.example.test/messages/scheduled')
        ->toContain('avoid sending a duplicate')
        ->toContain('GOOD SERVICE STAYS WITH PEOPLE');
});

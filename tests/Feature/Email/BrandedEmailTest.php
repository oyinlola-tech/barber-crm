<?php

use App\Dtos\Audit\DailyActivityDigestDto;
use App\Mail\OwnerTemporaryAccess;
use App\Mail\StaffInvitation;
use App\Models\User;
use App\Notifications\BrandedPasswordResetNotification;
use App\Notifications\CustomerMessageReceivedNotification;
use App\Notifications\CustomerReturnIntentionNotification;
use App\Notifications\DailyActivityDigestNotification;
use App\Notifications\MessageDeliveryUncertainNotification;
use App\Notifications\ReviewRecordedNotification;
use App\Notifications\StaffAccessChangedNotification;
use App\Notifications\VisitRecordedNotification;
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

it('renders completed visit email with a dashboard link and without customer details', function (): void {
    config(['app.url' => 'https://crm.example.test', 'app.name' => 'Barber CRM']);
    $user = User::factory()->create();
    $html = (new VisitRecordedNotification)->toMail($user)->render();

    expect($html)
        ->toContain('https://crm.example.test/dashboard')
        ->toContain('A completed customer visit was recorded.')
        ->toContain('customer’s private notes and contact details are not included')
        ->toContain('GOOD SERVICE STAYS WITH PEOPLE');
});

it('renders a privacy-safe review alert with the shared footer', function (): void {
    config(['app.url' => 'https://crm.example.test', 'app.name' => 'Barber CRM']);
    $user = User::factory()->create();
    $html = (new ReviewRecordedNotification)->toMail($user)->render();

    expect($html)
        ->toContain('https://crm.example.test/reviews')
        ->toContain('A customer shared feedback.')
        ->toContain('Customer names, phone numbers, ratings, and feedback text are not included')
        ->toContain('GOOD SERVICE STAYS WITH PEOPLE');
});

it('renders onboarding and operations emails through the complete shared footer', function (): void {
    config(['app.url' => 'https://crm.example.test', 'app.name' => 'Barber CRM']);
    $user = User::factory()->create();
    $emails = [
        (new OwnerTemporaryAccess('Example Barbershop', 'single-use-password'))->render(),
        (new StaffInvitation('https://crm.example.test/invite/token', 'Example Barbershop', 'temporary-password'))->render(),
        (new CustomerReturnIntentionNotification(41, 'Amina Customer', '+2348012345678', '2026-12-02'))->toMail($user)->render(),
        (new DailyActivityDigestNotification('digest-id', new DailyActivityDigestDto(1, 'Example Barbershop', '2026-11-28', ['customer.created' => 2], 2)))->toMail($user)->render(),
        (new StaffAccessChangedNotification('access-id', 'Staff Member', 'A staff account was activated.', '/operations/audit'))->toMail($user)->render(),
    ];

    foreach ($emails as $html) {
        expect($html)
            ->toContain('GOOD SERVICE STAYS WITH PEOPLE')
            ->toContain('Good service<br>deserves a return visit')
            ->toContain('Authorized staff only');
    }
});

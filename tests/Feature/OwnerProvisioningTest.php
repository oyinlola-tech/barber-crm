<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('provisions the first owner without exposing a password argument', function (): void {
    Notification::fake();
    $this->artisan('app:provision-owner', [
        '--name' => 'Barber Owner',
        '--email' => 'owner@example.test',
        '--business' => 'Corner Barber',
    ])
        ->expectsQuestion('Owner password (minimum 12 characters)', 'correct-horse-battery-staple')
        ->expectsOutputToContain('Owner account provisioned')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(1)
        ->and(Business::query()->firstOrFail()->name)->toBe('Corner Barber')
        ->and(User::query()->firstOrFail()->businesses()->firstOrFail()->pivot->role)->toBe('owner')
        ->and(User::query()->firstOrFail()->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo(User::query()->firstOrFail(), VerifyEmail::class);
});

it('does not expose public account registration', function (): void {
    $this->get('/register')->assertNotFound();
});

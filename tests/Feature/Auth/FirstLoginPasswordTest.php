<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function firstLoginOwner(bool $verified = true, ?string $expiresAt = null): array
{
    $business = Business::factory()->create(['is_active' => true]);
    $owner = User::factory()->create([
        'email' => 'first-login-owner@example.test',
        'email_verified_at' => $verified ? now() : null,
        'password' => 'Original Temporary Password 987654',
        'password_change_required' => true,
        'temporary_password_expires_at' => $expiresAt ?? now()->addHours(12),
    ]);
    $business->members()->attach($owner, [
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    return [$owner, $business];
}

it('allows an unverified privileged owner to reach the forced password page', function (): void {
    [$owner] = firstLoginOwner(verified: false);

    $this->actingAs($owner)
        ->get(route('account.first-login-password'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/first-login-password')
            ->has('expiresAt'));
});

it('allows an owner without MFA to reach the forced password page before MFA setup', function (): void {
    [$owner] = firstLoginOwner();

    $this->actingAs($owner)
        ->get(route('account.first-login-password'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/first-login-password'));
});

it('requires a first-login password change before any protected page', function (): void {
    [$owner] = firstLoginOwner();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertRedirect(route('account.first-login-password'));
});

it('clears temporary-password state after the password is changed', function (): void {
    [$owner] = firstLoginOwner();

    $this->actingAs($owner)->put(route('account.first-login-password.update'), [
        'current_password' => 'Original Temporary Password 987654',
        'password' => 'A Completely New Passphrase 7431',
        'password_confirmation' => 'A Completely New Passphrase 7431',
    ])->assertRedirect(route('dashboard'));

    expect($owner->fresh()->requiresPasswordChange())->toBeFalse()
        ->and($owner->fresh()->temporary_password_expires_at)->toBeNull()
        ->and(Hash::check('A Completely New Passphrase 7431', $owner->fresh()->password))->toBeTrue();
});

it('expires a temporary password at its stored deadline and ends the session', function (): void {
    [$owner] = firstLoginOwner(expiresAt: now()->subMinute()->toDateTimeString());

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Your temporary password expired. Request a password reset to continue.');

    $this->assertGuest();
});

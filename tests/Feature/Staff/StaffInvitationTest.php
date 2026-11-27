<?php

use App\Mail\StaffInvitation as StaffInvitationMail;
use App\Models\Business;
use App\Models\StaffInvitation;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Fortify;

uses(RefreshDatabase::class);

function staffInvitationOwner(): array
{
    test()->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    $owner->forceFill([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt('[]'),
        'two_factor_confirmed_at' => now(),
    ])->save();
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

    return [$owner, $business];
}

it('enforces case-insensitive email uniqueness in the database', function (): void {
    User::factory()->create(['email' => 'Sam.Barber@example.test']);

    expect(fn () => User::factory()->create(['email' => 'sam.barber@example.test']))
        ->toThrow(QueryException::class);
});

it('invites an employee and accepts a single-use expiring link without storing its raw token', function (): void {
    [$owner, $business] = staffInvitationOwner();
    expect($owner->hasBusinessPermission($business->getKey(), 'staff.invite'))->toBeTrue();
    Mail::fake();
    config(['mail.default' => 'smtp', 'app.url' => 'https://crm.example.test']);

    $response = $this->actingAs($owner)->post('/staff/invitations', [
        'name' => 'Sam Barber', 'email' => 'sam@example.test', 'role_slug' => 'barber',
    ]);
    $response->assertRedirect('/staff');

    $invitation = StaffInvitation::query()->firstOrFail();
    expect($invitation->business_id)->toBe($business->getKey())
        ->and($invitation->token_hash)->toHaveLength(64);

    $rawToken = null;
    Mail::assertSent(StaffInvitationMail::class, function (StaffInvitationMail $mail) use (&$rawToken): bool {
        $rawToken = parse_url($mail->invitationUrl, PHP_URL_FRAGMENT);

        return true;
    });
    expect($rawToken)->toBeString()->toHaveLength(64)
        ->and($invitation->token_hash)->toBe(hash('sha256', (string) $rawToken))
        ->and($invitation->token_hash)->not->toBe($rawToken);

    $this->post('/staff/invitations/accept', [
        'token' => $rawToken, 'password' => 'A secure passphrase 9842', 'password_confirmation' => 'A secure passphrase 9842',
    ])->assertRedirect('/login');

    $employee = User::query()->where('email', 'sam@example.test')->firstOrFail();
    expect($employee->hasBusinessPermission($business->getKey(), 'visits.create'))->toBeTrue()
        ->and(StaffProfile::query()->where('user_id', $employee->getKey())->exists())->toBeTrue()
        ->and($employee->hasVerifiedEmail())->toBeTrue()
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();

    $this->post('/staff/invitations/accept', [
        'token' => $rawToken, 'password' => 'A secure passphrase 9842', 'password_confirmation' => 'A secure passphrase 9842',
    ])->assertSessionHasErrors('token');
});

it('renders the branded staff invitation with a secure setup action and footer', function (): void {
    config(['app.url' => 'https://crm.example.test', 'app.name' => 'Barber CRM']);
    $mail = new StaffInvitationMail('https://crm.example.test/staff/invitation#one-time-token', 'Northside Barbers');
    $html = $mail->render();

    expect($html)
        ->toContain('Northside Barbers')
        ->toContain('Set up staff access')
        ->toContain('only be used once')
        ->toContain('Good service stays with you')
        ->toContain('barber-crm-mark.svg');
});

it('rejects invitation delivery when mail is configured to log secrets', function (): void {
    [$owner] = staffInvitationOwner();
    config(['mail.default' => 'log']);
    Mail::fake();

    $this->actingAs($owner)->post('/staff/invitations', [
        'name' => 'Sam Barber', 'email' => 'sam@example.test', 'role_slug' => 'barber',
    ])->assertSessionHasErrors('email');

    expect(StaffInvitation::query()->count())->toBe(0);
});

it('rejects invitation email addresses that differ from an existing account only by case', function (): void {
    [$owner] = staffInvitationOwner();
    Mail::fake();
    config(['mail.default' => 'smtp']);
    User::factory()->create(['email' => 'Sam.Barber@example.test']);

    $this->actingAs($owner)->post('/staff/invitations', [
        'name' => 'Sam Barber', 'email' => 'sam.barber@example.test', 'role_slug' => 'barber',
    ])->assertSessionHasErrors('email');

    expect(StaffInvitation::query()->count())->toBe(0);
});

it('does not accept an invitation when a case-insensitive matching account already exists', function (): void {
    [$owner, $business] = staffInvitationOwner();
    $invitation = StaffInvitation::query()->create([
        'business_id' => $business->getKey(),
        'invited_by' => $owner->getKey(),
        'name' => 'Sam Barber',
        'email' => 'sam.barber@example.test',
        'role_slug' => 'barber',
        'token_hash' => hash('sha256', 'case-insensitive-invitation-token'),
        'expires_at' => now()->addDay(),
    ]);
    User::factory()->create(['email' => 'Sam.Barber@example.test']);

    $this->post('/staff/invitations/accept', [
        'token' => 'case-insensitive-invitation-token',
        'password' => 'A secure passphrase 9842',
        'password_confirmation' => 'A secure passphrase 9842',
    ])->assertSessionHasErrors('token');

    expect(User::query()->whereRaw('LOWER(email) = ?', ['sam.barber@example.test'])->count())->toBe(1)
        ->and($invitation->fresh()->accepted_at)->toBeNull();
});

it('prevents expired invitation reuse', function (): void {
    [$owner, $business] = staffInvitationOwner();
    $invitation = StaffInvitation::query()->create([
        'business_id' => $business->getKey(), 'invited_by' => $owner->getKey(), 'name' => 'Sam Barber',
        'email' => 'sam@example.test', 'role_slug' => 'barber', 'token_hash' => hash('sha256', 'x'.str_repeat('y', 63)),
        'expires_at' => now()->subMinute(),
    ]);

    $this->post('/staff/invitations/accept', [
        'token' => 'x'.str_repeat('y', 63), 'password' => 'A secure passphrase 9842', 'password_confirmation' => 'A secure passphrase 9842',
    ])->assertSessionHasErrors('token');

    expect($invitation->fresh()->accepted_at)->toBeNull()
        ->and(User::query()->where('email', 'sam@example.test')->exists())->toBeFalse();
});

<?php

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\Commands\RevokeAccountSessionCommand;
use App\Services\Auth\Queries\ListAccountSessionsQuery;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

function accountSecurityBusiness(): Business
{
    return Business::factory()->create();
}

function attachSecurityUser(Business $business, User $user, string $role): void
{
    $business->members()->attach($user, [
        'role' => $role,
        'status' => 'active',
        'joined_at' => now(),
    ]);
}

function insertAccountSession(string $id, int $userId, ?int $lastActivity = null): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test browser',
        'payload' => '',
        'last_activity' => $lastActivity ?? now()->timestamp,
    ]);
}

it('hashes account passwords with the configured Argon2id driver', function (): void {
    $user = User::factory()->create(['password' => 'an account password']);

    expect(config('hashing.driver'))->toBe('argon2id')
        ->and(Hash::info($user->password)['algoName'])->toBe('argon2id');
});

it('requires confirmed two-factor authentication for the owner and lets them reach the setup page', function (): void {
    $business = accountSecurityBusiness();
    $owner = User::factory()->create();
    attachSecurityUser($business, $owner, 'owner');

    $this->actingAs($owner)->get('/dashboard')->assertRedirect(route('account.security'));
    $sessionResponse = $this->get('/settings/security');
    $sessionResponse
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security')
            ->where('twoFactor.enabled', false)
            ->where('twoFactor.pendingConfirmation', false)
            ->has('sessions'));
});

it('does not let an owner without confirmed MFA revoke sessions through the setup exemption', function (): void {
    $business = accountSecurityBusiness();
    $owner = User::factory()->create();
    attachSecurityUser($business, $owner, 'owner');
    insertAccountSession('ownerrevocationtarget', (int) $owner->getKey());

    $this->actingAs($owner)
        ->delete('/settings/security/sessions/ownerrevocationtarget')
        ->assertRedirect(route('account.security'));

    expect(DB::table('sessions')->where('id', 'ownerrevocationtarget')->exists())->toBeTrue();
});

it('does not force authenticator enrollment on a non-privileged viewer account', function (): void {
    $business = accountSecurityBusiness();
    $viewer = User::factory()->create();
    attachSecurityUser($business, $viewer, 'viewer');

    $this->actingAs($viewer)->get('/dashboard')->assertOk();
});

it('requires MFA for a custom security administrator role', function (): void {
    $business = accountSecurityBusiness();
    $administrator = User::factory()->create();
    Role::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Security administrator',
        'slug' => 'security-administrator',
        'permissions' => ['roles.*'],
    ]);
    attachSecurityUser($business, $administrator, 'security-administrator');

    $this->actingAs($administrator)->get('/dashboard')->assertRedirect(route('account.security'));
});

it('requires MFA for staff with permission to remove staff access', function (): void {
    $business = accountSecurityBusiness();
    $administrator = User::factory()->create();
    Role::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Staff access administrator',
        'slug' => 'staff-access-administrator',
        'permissions' => ['staff.delete'],
    ]);
    attachSecurityUser($business, $administrator, 'staff-access-administrator');

    $this->actingAs($administrator)->get('/dashboard')->assertRedirect(route('account.security'));
});

it('supports confirmed TOTP enrollment and one-time recovery-code sign in', function (): void {
    $business = accountSecurityBusiness();
    $owner = User::factory()->create();
    attachSecurityUser($business, $owner, 'owner');
    $this->actingAs($owner);

    $this->post('/settings/security/confirm-password', ['password' => 'password'])->assertRedirect();
    $this->post('/user/two-factor-authentication')->assertRedirect();
    $this->get('/user/two-factor-qr-code')->assertOk()->assertJsonStructure(['svg', 'url']);

    $secret = Fortify::currentEncrypter()->decrypt($owner->fresh()->two_factor_secret);
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    $this->post('/user/confirmed-two-factor-authentication', ['code' => $code])->assertRedirect();

    $owner = $owner->fresh();
    expect($owner->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($owner->hasRecoveryCodes())->toBeTrue();
    $recoveryCode = $owner->recoveryCodes()[0];

    $this->post('/logout')->assertRedirect('/');
    $this->post('/login', ['email' => $owner->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));
    $this->post('/two-factor-challenge', ['recovery_code' => $recoveryCode])
        ->assertRedirect('/dashboard');

    expect(auth()->user()?->getKey())->toBe($owner->getKey())
        ->and($owner->fresh()->recoveryCodes())->not->toContain($recoveryCode);
});

it('requires email verification and accepts only the signed verification link for that account', function (): void {
    $business = accountSecurityBusiness();
    $user = User::factory()->unverified()->create();
    attachSecurityUser($business, $user, 'viewer');
    Notification::fake();

    $this->actingAs($user)->get('/dashboard')->assertRedirect('/email/verify');
    $this->get('/email/verify')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/verify-email'));
    $this->post('/email/verification-notification')->assertRedirect();
    Notification::assertSentTo($user, VerifyEmail::class);

    $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(5), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->get($verificationUrl)->assertRedirect();
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->get('/dashboard')->assertOk();
});

it('lists only the authenticated user sessions and allows revoking another session', function (): void {
    $business = accountSecurityBusiness();
    $viewer = User::factory()->create();
    $anotherUser = User::factory()->create();
    attachSecurityUser($business, $viewer, 'viewer');
    attachSecurityUser($business, $anotherUser, 'barber');

    $this->actingAs($viewer);
    insertAccountSession('viewer-current-session', (int) $viewer->getKey(), now()->timestamp + 60);
    insertAccountSession('otherbrowsersession', (int) $viewer->getKey(), now()->timestamp - 60);
    insertAccountSession('unrelated-user-session', (int) $anotherUser->getKey());

    $sessionResponse = $this->get('/settings/security');
    $sessionResponse
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sessions', 2));

    $listedSessions = app(ListAccountSessionsQuery::class)
        ->execute((int) $viewer->getKey(), 'viewer-current-session');
    expect(collect($listedSessions)->firstWhere('id', 'viewer-current-session')['is_current'])->toBeTrue();

    $this->delete('/settings/security/sessions/otherbrowsersession')->assertRedirect();

    expect(DB::table('sessions')->where('id', 'otherbrowsersession')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'unrelated-user-session')->exists())->toBeTrue()
        ->and(DB::table('audit_logs')->where('action', 'account.session_revoked')->exists())->toBeTrue();

    expect(app(RevokeAccountSessionCommand::class)->execute(
        (int) $viewer->getKey(), 'viewer-current-session', 'viewer-current-session',
    ))->toBeFalse();
});

it('requires the password before revoking all other sessions and preserves the current session', function (): void {
    $business = accountSecurityBusiness();
    $user = User::factory()->create();
    attachSecurityUser($business, $user, 'viewer');
    $this->actingAs($user);

    insertAccountSession('other-one', (int) $user->getKey());

    $wrongPasswordResponse = $this->delete('/settings/security/sessions', ['password' => 'wrong-password']);
    $wrongPasswordResponse->assertSessionHasErrors('password');
    expect(DB::table('sessions')->where('id', 'other-one')->exists())->toBeTrue();

    $this->delete('/settings/security/sessions', ['password' => 'password'])->assertRedirect();

    expect(DB::table('sessions')->where('id', 'other-one')->exists())->toBeFalse()
        ->and(DB::table('audit_logs')->where('action', 'account.sessions_revoked')->exists())->toBeTrue();
});

<?php

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Role;
use App\Models\StaffInvitation;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Fortify;

uses(RefreshDatabase::class);

function lifecycleUser(string $name, string $email): User
{
    $user = User::factory()->create(['name' => $name, 'email' => $email, 'email_verified_at' => now()]);
    $user->forceFill([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt('[]'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $user;
}

function lifecycleBusiness(): array
{
    test()->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create(['is_active' => true]);
    $owner = lifecycleUser('Shop Owner', 'owner@barber.test');
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

    return [$business, $owner];
}

it('revokes a pending staff invitation and rejects its former token', function (): void {
    [$business, $owner] = lifecycleBusiness();
    $invitation = StaffInvitation::query()->create([
        'business_id' => $business->getKey(),
        'invited_by' => $owner->getKey(),
        'name' => 'Barber Sam',
        'email' => 'sam@barber.test',
        'role_slug' => 'barber',
        'token_hash' => hash('sha256', 'x'.str_repeat('y', 63)),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)->delete(route('staff.invitations.revoke', $invitation))
        ->assertRedirect(route('staff.index'));

    expect($invitation->refresh()->revoked_at)->not->toBeNull()
        ->and(DB::table('audit_logs')->where('business_id', $business->getKey())->where('action', 'staff.invitation.revoked')->exists())->toBeTrue();

    $this->post(route('staff.invitations.accept'), [
        'token' => 'x'.str_repeat('y', 63),
        'password' => 'A secure passphrase 9842',
        'password_confirmation' => 'A secure passphrase 9842',
    ])->assertSessionHasErrors('token');
});

it('allows a manager to edit staff name and phone while preserving login email and auditing the change', function (): void {
    [$business] = lifecycleBusiness();
    $manager = lifecycleUser('Shop Manager', 'manager@barber.test');
    $employee = User::factory()->create(['name' => 'Sam Barber', 'email' => 'sam@barber.test']);
    $business->members()->attach($manager, ['role' => 'manager', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($employee, ['role' => 'barber', 'status' => 'active', 'joined_at' => now()]);
    $profile = StaffProfile::query()->create([
        'business_id' => $business->getKey(),
        'user_id' => $employee->getKey(),
        'name' => 'Sam Barber',
        'email' => 'sam@barber.test',
        'status' => 'active',
    ]);

    $this->actingAs($manager)->patch(route('staff.profile', $profile), [
        'name' => 'Samuel Barber',
        'phone' => '+2348031234567',
    ])->assertRedirect(route('staff.index'));

    expect($profile->refresh()->name)->toBe('Samuel Barber')
        ->and($profile->phone)->toBe('+2348031234567')
        ->and($employee->fresh()->name)->toBe('Samuel Barber')
        ->and($employee->fresh()->email)->toBe('sam@barber.test')
        ->and(DB::table('audit_logs')->where('business_id', $business->getKey())->where('action', 'staff.profile_updated')->exists())->toBeTrue();
});

it('prevents a manager from editing the protected owner profile', function (): void {
    [$business, $owner] = lifecycleBusiness();
    $manager = lifecycleUser('Shop Manager', 'manager@barber.test');
    $business->members()->attach($manager, ['role' => 'manager', 'status' => 'active', 'joined_at' => now()]);
    $profile = StaffProfile::query()->create([
        'business_id' => $business->getKey(),
        'user_id' => $owner->getKey(),
        'name' => 'Shop Owner',
        'email' => 'owner@barber.test',
        'status' => 'active',
    ]);

    $this->actingAs($manager)->patch(route('staff.profile', $profile), [
        'name' => 'Changed Owner',
        'phone' => null,
    ])->assertForbidden();

    expect($profile->refresh()->name)->toBe('Shop Owner')
        ->and($owner->fresh()->name)->toBe('Shop Owner');
});

it('renders the complete permission catalogue on the authorized roles page', function (): void {
    [$business, $owner] = lifecycleBusiness();
    $this->actingAs($owner)->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('roles/index')
            ->where('permissions', BusinessRole::permissions()));
});

it('creates, updates, and deletes a custom role through the authorized HTTP workflow', function (): void {
    [$business, $owner] = lifecycleBusiness();

    $this->actingAs($owner)->post(route('roles.store'), [
        'name' => 'Senior Barber',
        'permissions' => ['customers.view'],
    ])->assertRedirect(route('roles.index'));

    $role = Role::query()->where('business_id', $business->getKey())->where('slug', 'senior-barber')->firstOrFail();
    expect($role->permissions)->toBe(['customers.view']);

    $invitation = StaffInvitation::query()->create([
        'business_id' => $business->getKey(),
        'invited_by' => $owner->getKey(),
        'name' => 'New Barber',
        'email' => 'new-barber@barber.test',
        'role_slug' => 'senior-barber',
        'token_hash' => hash('sha256', 'role-lifecycle-token'),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)->put(route('roles.update', $role), [
        'name' => 'Lead Barber',
        'permissions' => ['customers.view', 'visits.view'],
    ])->assertRedirect(route('roles.index'));

    expect($role->fresh()->slug)->toBe('lead-barber')
        ->and($role->fresh()->permissions)->toBe(['customers.view', 'visits.view'])
        ->and($invitation->fresh()->role_slug)->toBe('lead-barber');

    $this->actingAs($owner)->delete(route('roles.destroy', $role))
        ->assertSessionHasErrors('role');
    expect($role->fresh())->not->toBeNull();

    $invitation->forceFill(['revoked_at' => now()])->save();
    $this->actingAs($owner)->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'));

    expect($role->fresh())->toBeNull()
        ->and(DB::table('audit_logs')->where('business_id', $business->getKey())->whereIn('action', [
            'roles.created', 'roles.updated', 'roles.deleted',
        ])->count())->toBe(3);
});

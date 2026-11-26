<?php

use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Staff\Commands\AssignStaffRoleCommand;
use App\Services\Staff\Commands\CreateRoleCommand;
use App\Services\Staff\Commands\SetStaffStatusCommand;
use App\Services\Staff\Commands\UpdateRoleCommand;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function staffAccessFixture(): array
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

    return [$business, $owner];
}

it('suspends staff, revokes their database sessions, and records an audit event', function (): void {
    [$business, $owner] = staffAccessFixture();
    $employee = User::factory()->create();
    $business->members()->attach($employee, ['role' => 'barber', 'status' => 'active', 'joined_at' => now()]);
    $profile = StaffProfile::query()->create([
        'business_id' => $business->getKey(), 'user_id' => $employee->getKey(), 'name' => $employee->name,
        'email' => $employee->email, 'status' => 'active',
    ]);
    DB::table('sessions')->insert([
        'id' => 'employee-session', 'user_id' => $employee->getKey(), 'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    app(SetStaffStatusCommand::class)->execute($owner, $business, (int) $profile->getKey(), false);

    expect($employee->fresh()->hasBusinessPermission($business->getKey(), 'customers.view'))->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'employee-session')->exists())->toBeFalse()
        ->and($profile->fresh()->status)->toBe('suspended')
        ->and(DB::table('audit_logs')->where('action', 'staff.suspended')->exists())->toBeTrue();

    $this->actingAs($employee)->get('/dashboard')->assertRedirect('/login')->assertSessionHasErrors('email');
    expect(auth()->guest())->toBeTrue();
});

it('protects the owner and prevents a staff member from suspending their own account', function (): void {
    [$business, $owner] = staffAccessFixture();
    $ownerProfile = StaffProfile::query()->create([
        'business_id' => $business->getKey(), 'user_id' => $owner->getKey(), 'name' => $owner->name,
        'email' => $owner->email, 'status' => 'active',
    ]);
    expect(fn () => app(SetStaffStatusCommand::class)->execute($owner, $business, (int) $ownerProfile->getKey(), false))
        ->toThrow(ValidationException::class);

    $manager = User::factory()->create();
    $business->members()->attach($manager, ['role' => 'manager', 'status' => 'active', 'joined_at' => now()]);
    $managerProfile = StaffProfile::query()->create([
        'business_id' => $business->getKey(), 'user_id' => $manager->getKey(), 'name' => $manager->name,
        'email' => $manager->email, 'status' => 'active',
    ]);
    expect(fn () => app(SetStaffStatusCommand::class)->execute($manager, $business, (int) $managerProfile->getKey(), false))
        ->toThrow(ValidationException::class);
});

it('allows delegation only within the acting user permissions and never assigns ownership', function (): void {
    [$business, $owner] = staffAccessFixture();
    $manager = User::factory()->create();
    $business->members()->attach($manager, ['role' => 'manager', 'status' => 'active', 'joined_at' => now()]);
    $employee = User::factory()->create();
    $business->members()->attach($employee, ['role' => 'barber', 'status' => 'active', 'joined_at' => now()]);
    $profile = StaffProfile::query()->create([
        'business_id' => $business->getKey(), 'user_id' => $employee->getKey(), 'name' => $employee->name,
        'email' => $employee->email, 'status' => 'active',
    ]);
    $elevatedRole = app(CreateRoleCommand::class)->execute($owner, $business, 'Security administrator', ['settings.update']);

    expect(fn () => app(AssignStaffRoleCommand::class)->execute($manager, $business, (int) $profile->getKey(), $elevatedRole->slug))
        ->toThrow(HttpException::class);
    expect(fn () => app(AssignStaffRoleCommand::class)->execute($owner, $business, (int) $profile->getKey(), 'owner'))
        ->toThrow(HttpException::class);

    app(AssignStaffRoleCommand::class)->execute($manager, $business, (int) $profile->getKey(), 'viewer');
    expect(BusinessMember::query()->where('business_id', $business->getKey())->where('user_id', $employee->getKey())->value('role'))->toBe('viewer');
});

it('reserves the owner role slug from custom role creation and renaming', function (): void {
    [$business, $owner] = staffAccessFixture();
    $employee = User::factory()->create();
    $business->members()->attach($employee, ['role' => 'custom_barber', 'status' => 'active', 'joined_at' => now()]);
    $customRole = Role::query()->create([
        'business_id' => $business->getKey(),
        'slug' => 'custom_barber',
        'name' => 'Custom barber',
        'permissions' => ['customers.view'],
    ]);

    expect(fn () => app(CreateRoleCommand::class)->execute($owner, $business, 'Owner', ['customers.view']))
        ->toThrow(ValidationException::class);
    expect(fn () => app(UpdateRoleCommand::class)->execute(
        $owner,
        $business,
        (int) $customRole->getKey(),
        'Owner',
        ['customers.view'],
    ))->toThrow(ValidationException::class);

    expect($employee->fresh()->hasBusinessPermission((int) $business->getKey(), 'customers.view'))->toBeTrue()
        ->and($employee->fresh()->hasBusinessPermission((int) $business->getKey(), 'staff.delete'))->toBeFalse()
        ->and($customRole->fresh()->slug)->toBe('custom_barber');
});

it('serves staff and role pages only to authorized staff', function (): void {
    [$business, $owner] = staffAccessFixture();
    $viewer = User::factory()->create();
    $business->members()->attach($viewer, ['role' => 'viewer', 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($viewer)->get('/staff')->assertForbidden();
    $this->actingAs($viewer)->get('/roles')->assertForbidden();
    $this->actingAs($owner)->get('/staff')->assertOk();
    $this->actingAs($owner)->get('/roles')->assertOk();
});

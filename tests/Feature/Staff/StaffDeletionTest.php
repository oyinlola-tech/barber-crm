<?php

use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Staff\Commands\DeleteStaffMemberCommand;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function staffDeletionOwner(Business $business): User
{
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $owner->forceFill([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt('[]'),
        'two_factor_confirmed_at' => now(),
    ])->save();
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

    return $owner;
}

it('revokes access and redacts a removed staff account while retaining its profile record', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create(['is_active' => true]);
    $owner = staffDeletionOwner($business);
    $employee = User::factory()->create(['email' => 'barber@example.test', 'name' => 'Barber Name']);
    $business->members()->attach($employee, ['role' => 'barber', 'status' => 'active', 'joined_at' => now()]);
    $profile = StaffProfile::query()->create([
        'business_id' => $business->getKey(),
        'user_id' => $employee->getKey(),
        'name' => 'Barber Name',
        'email' => 'barber@example.test',
        'phone' => '+2348012345678',
        'status' => 'active',
    ]);
    DB::table('sessions')->insert([
        'id' => 'deleted-staff-session', 'user_id' => $employee->getKey(), 'payload' => '',
        'last_activity' => now()->timestamp,
    ]);
    DB::table('password_reset_tokens')->insert([
        'email' => $employee->email, 'token' => 'one-time-token', 'created_at' => now(),
    ]);

    $this->actingAs($owner)->delete(route('staff.destroy', $profile))->assertRedirect(route('staff.index'));

    $formerAccount = $employee->fresh();
    expect($profile->fresh()->status)->toBe('deleted')
        ->and($profile->fresh()->user_id)->toBe($employee->getKey())
        ->and($profile->fresh()->name)->toBe('Deleted staff member')
        ->and($profile->fresh()->email)->toBeNull()
        ->and($profile->fresh()->phone)->toBeNull()
        ->and($formerAccount->name)->toBe('Deleted staff member')
        ->and($formerAccount->email)->toEndWith('@redacted.invalid')
        ->and($formerAccount->two_factor_secret)->toBeNull()
        ->and($formerAccount->two_factor_recovery_codes)->toBeNull()
        ->and(BusinessMember::query()->where('business_id', $business->getKey())->where('user_id', $employee->getKey())->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'deleted-staff-session')->exists())->toBeFalse()
        ->and(DB::table('password_reset_tokens')->where('email', 'barber@example.test')->exists())->toBeFalse()
        ->and(DB::table('audit_logs')->where('business_id', $business->getKey())->where('action', 'staff.deleted')->exists())->toBeTrue();
});

it('protects the owner and requires staff.delete even when the command is called directly', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $owner = staffDeletionOwner($business);
    $profile = StaffProfile::query()->create([
        'business_id' => $business->getKey(), 'user_id' => $owner->getKey(),
        'name' => $owner->name, 'email' => $owner->email, 'status' => 'active',
    ]);

    expect(fn () => app(DeleteStaffMemberCommand::class)->execute($owner, $business, (int) $profile->getKey()))
        ->toThrow(ValidationException::class);

    $employee = User::factory()->create();
    $business->members()->attach($employee, ['role' => 'barber', 'status' => 'active', 'joined_at' => now()]);
    $employeeProfile = StaffProfile::query()->create([
        'business_id' => $business->getKey(), 'user_id' => $employee->getKey(),
        'name' => $employee->name, 'email' => $employee->email, 'status' => 'active',
    ]);
    $manager = User::factory()->create();
    $business->members()->attach($manager, ['role' => 'manager', 'status' => 'active', 'joined_at' => now()]);

    expect(fn () => app(DeleteStaffMemberCommand::class)->execute($manager, $business, (int) $employeeProfile->getKey()))
        ->toThrow(HttpException::class);
    expect($employeeProfile->fresh()->status)->toBe('active');
});

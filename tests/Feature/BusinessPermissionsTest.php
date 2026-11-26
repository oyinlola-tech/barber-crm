<?php

use App\Models\Business;
use App\Models\User;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses centralized role permission identifiers for business staff', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $manager = User::factory()->create();
    $viewer = User::factory()->create();
    $business->members()->attach($manager, ['role' => 'manager', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($viewer, ['role' => 'viewer', 'status' => 'active', 'joined_at' => now()]);

    expect($manager->hasBusinessPermission($business->getKey(), 'customers.update'))->toBeTrue()
        ->and($manager->hasBusinessPermission($business->getKey(), 'settings.update'))->toBeFalse()
        ->and($viewer->hasBusinessPermission($business->getKey(), 'customers.view'))->toBeTrue()
        ->and($viewer->hasBusinessPermission($business->getKey(), 'customers.update'))->toBeFalse();
});

it('rejects permission checks for suspended memberships', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'suspended', 'joined_at' => now()]);

    expect($user->hasBusinessPermission($business->getKey(), 'customers.view'))->toBeFalse();
});

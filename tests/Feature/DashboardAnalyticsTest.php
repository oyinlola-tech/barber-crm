<?php

use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shows real single-shop customer metrics to an active staff member', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $user = User::factory()->create();
    $business = Business::factory()->create();
    $business->members()->attach($user, ['role' => 'viewer', 'status' => 'active', 'joined_at' => now()]);
    Customer::factory()->create(['business_id' => $business->getKey()]);

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/index')
        ->where('business.name', $business->name)
        ->where('analytics.customer_count', 1)
        ->where('analytics.visit_count', 0)
        ->where('analytics.whatsapp_connected', false));
});

it('shares navigation links only for permissions granted to the signed-in role', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $viewer = User::factory()->create();
    $business->members()->attach($viewer, ['role' => 'viewer', 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($viewer)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('currentBusiness.name', $business->name)
        ->where('navigation', [
            ['label' => 'Dashboard', 'href' => '/dashboard'],
            ['label' => 'Customers', 'href' => '/customers'],
            ['label' => 'Reviews', 'href' => '/reviews'],
            ['label' => 'Analytics', 'href' => '/analytics'],
            ['label' => 'Account security', 'href' => '/settings/security'],
        ]));
});

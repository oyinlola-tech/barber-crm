<?php

use App\Events\Customers\CustomerReturnIntentionRecorded;
use App\Listeners\Notifications\NotifyStaffOfCustomerReturnIntention;
use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\CustomerReturnIntentionNotification;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('notifies active staff with customer access and keeps the notice explicitly non-reserving', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    Notification::fake();

    $business = Business::factory()->create();
    $actor = User::factory()->create();
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $receptionist = User::factory()->create();
    $suspendedOwner = User::factory()->create();

    $business->members()->attach($actor, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($viewer, ['role' => 'viewer', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($receptionist, ['role' => 'receptionist', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($suspendedOwner, ['role' => 'owner', 'status' => 'suspended', 'joined_at' => now()]);

    $customer = Customer::factory()->create([
        'business_id' => $business->getKey(),
        'first_name' => 'Ayo',
        'last_name' => 'Bello',
        'phone' => '+2348012345678',
    ]);

    (new NotifyStaffOfCustomerReturnIntention)->handle(new CustomerReturnIntentionRecorded(
        $customer,
        (int) $actor->getKey(),
        '2026-12-04',
    ));

    Notification::assertSentTo($owner, CustomerReturnIntentionNotification::class);
    Notification::assertSentTo($receptionist, CustomerReturnIntentionNotification::class);
    Notification::assertNotSentTo($actor, CustomerReturnIntentionNotification::class);
    Notification::assertSentTo($viewer, CustomerReturnIntentionNotification::class);
    Notification::assertNotSentTo($suspendedOwner, CustomerReturnIntentionNotification::class);

    $notification = new CustomerReturnIntentionNotification(
        (int) $customer->getKey(),
        'Ayo Bello',
        '+2348012345678',
        '2026-12-04',
    );

    expect($notification->toArray($owner))
        ->toMatchArray([
            'title' => 'Customer plans to return',
            'customer_id' => (int) $customer->getKey(),
            'intended_return_date' => '2026-12-04',
            'url' => '/customers/'.$customer->getKey(),
        ])
        ->and($notification->toMail($owner)->render())
        ->toContain('Ayo Bello')
        ->toContain('+2348012345678')
        ->toContain('2026-12-04')
        ->toContain('does not reserve a time');
});

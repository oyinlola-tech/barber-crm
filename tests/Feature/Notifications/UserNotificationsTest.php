<?php

use App\Events\Visits\VisitCompleted;
use App\Listeners\Notifications\NotifyStaffAfterVisitRecorded;
use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\VisitRecordedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lists only the signed-in users notifications and exposes an allowlisted payload', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $other = User::factory()->create();
    confirmNotificationsMfa($user);
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($other, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $user->notify(new VisitRecordedNotification);
    $other->notify(new VisitRecordedNotification);

    $this->actingAs($user)->get('/notifications')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->has('notifications', 1)
            ->where('unreadCount', 1)
            ->where('notifications.0.title', 'Customer visit recorded')
            ->missing('notifications.0.data'));
});

it('marks only the signed-in users notification as read', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $other = User::factory()->create();
    confirmNotificationsMfa($user);
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($other, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $user->notify(new VisitRecordedNotification);
    $other->notify(new VisitRecordedNotification);
    $ownNotification = $user->unreadNotifications()->firstOrFail();
    $otherNotification = $other->unreadNotifications()->firstOrFail();

    $this->actingAs($user)->post('/notifications/'.$ownNotification->getKey().'/read')->assertRedirect('/notifications');
    $this->assertDatabaseHas('notifications', ['id' => $ownNotification->getKey(), 'read_at' => now()->format('Y-m-d H:i:s')]);

    $this->actingAs($user)->post('/notifications/'.$otherNotification->getKey().'/read')->assertNotFound();
    $this->assertDatabaseHas('notifications', ['id' => $otherNotification->getKey(), 'read_at' => null]);
});

it('can mark the current users unread notifications as read in one operation', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->create();
    confirmNotificationsMfa($user);
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $user->notify(new VisitRecordedNotification);
    $user->notify(new VisitRecordedNotification);

    $this->actingAs($user)->post('/notifications/read-all')->assertRedirect('/notifications');

    expect($user->unreadNotifications()->count())->toBe(0);
});

function confirmNotificationsMfa(User $user): void
{
    $user->forceFill([
        'two_factor_secret' => encrypt('notification-test-secret'),
        'two_factor_confirmed_at' => now(),
    ])->save();
}

it('notifies other active dashboard staff after a completed visit without including customer details', function (): void {
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $inactive = User::factory()->create();
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($actor, ['role' => 'manager', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($inactive, ['role' => 'owner', 'status' => 'suspended', 'joined_at' => now()]);
    $visit = Visit::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => Customer::factory()->create(['business_id' => $business->getKey()])->getKey(),
        'recorded_by' => $actor->getKey(),
        'visited_at' => now(),
    ]);

    (new NotifyStaffAfterVisitRecorded)->handle(new VisitCompleted($visit));

    expect($owner->notifications()->count())->toBe(1)
        ->and($actor->notifications()->count())->toBe(0)
        ->and($inactive->notifications()->count())->toBe(0);
    $notification = $owner->notifications()->firstOrFail();
    expect($notification->data)->toBe([
        'title' => 'Customer visit recorded',
        'message' => 'A staff member recorded a completed customer visit.',
        'url' => '/dashboard',
    ])->and($notification)->toBeInstanceOf(DatabaseNotification::class);
});

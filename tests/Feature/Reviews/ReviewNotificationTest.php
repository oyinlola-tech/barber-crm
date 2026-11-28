<?php

use App\Events\Reviews\ReviewRecorded;
use App\Listeners\Notifications\NotifyStaffAfterReviewRecorded;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ReviewRecordedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('notifies active staff who can view reviews without exposing feedback content', function (): void {
    $business = Business::factory()->create();
    $actor = reviewNotificationMember($business, 'review-writer', ['reviews.create', 'reviews.view']);
    $reviewer = reviewNotificationMember($business, 'review-recipient', ['reviews.view']);
    $viewer = reviewNotificationMember($business, 'dashboard-viewer', ['dashboard.view']);
    $suspended = reviewNotificationMember($business, 'suspended-reviewer', ['reviews.view'], 'suspended');
    $review = Review::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => Customer::factory()->create(['business_id' => $business->getKey()])->getKey(),
        'rating' => 1,
        'body' => 'Private customer feedback',
        'source' => 'internal',
        'reviewed_at' => now(),
    ]);

    Notification::fake();
    app(NotifyStaffAfterReviewRecorded::class)->handle(new ReviewRecorded(
        (int) $review->getKey(),
        (int) $business->getKey(),
        (int) $actor->getKey(),
    ));

    Notification::assertSentTo($reviewer, ReviewRecordedNotification::class);
    Notification::assertNotSentTo($actor, ReviewRecordedNotification::class);
    Notification::assertNotSentTo($viewer, ReviewRecordedNotification::class);
    Notification::assertNotSentTo($suspended, ReviewRecordedNotification::class);
});

function reviewNotificationMember(Business $business, string $slug, array $permissions, string $status = 'active'): User
{
    $user = User::factory()->create();
    Role::query()->create([
        'business_id' => $business->getKey(),
        'name' => ucfirst($slug),
        'slug' => $slug,
        'permissions' => $permissions,
    ]);
    $business->members()->attach($user, [
        'role' => $slug,
        'status' => $status,
        'joined_at' => now(),
    ]);

    return $user;
}

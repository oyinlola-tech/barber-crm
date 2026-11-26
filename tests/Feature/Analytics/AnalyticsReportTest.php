<?php

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Review;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('returns real date-filtered metrics scoped to the configured shop', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create(['timezone' => 'Africa/Lagos']);
    $otherBusiness = Business::factory()->create(['is_active' => false]);
    $viewer = User::factory()->create();
    $business->members()->attach($viewer, ['role' => 'viewer', 'status' => 'active', 'joined_at' => now()]);
    $newCustomer = Customer::factory()->create([
        'business_id' => $business->getKey(),
        'created_at' => '2026-10-31 23:30:00',
    ]);
    $secondCustomer = Customer::factory()->create([
        'business_id' => $business->getKey(),
        'created_at' => '2026-10-01 00:00:00',
    ]);
    Customer::factory()->create([
        'business_id' => $otherBusiness->getKey(),
        'created_at' => '2026-11-01 01:00:00',
    ]);

    foreach (['2026-11-01 12:00:00', '2026-11-02 12:00:00'] as $visitedAt) {
        Visit::query()->create([
            'business_id' => $business->getKey(),
            'customer_id' => $newCustomer->getKey(),
            'visited_at' => $visitedAt,
        ]);
    }
    Visit::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $secondCustomer->getKey(),
        'visited_at' => '2026-11-03 12:00:00',
    ]);
    Visit::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $secondCustomer->getKey(),
        'visited_at' => '2026-10-31 22:59:00',
    ]);
    $thirdCustomer = Customer::factory()->create(['business_id' => $business->getKey()]);
    Visit::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $thirdCustomer->getKey(),
        'visited_at' => '2026-10-30 10:00:00',
    ]);
    Visit::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $thirdCustomer->getKey(),
        'visited_at' => '2026-11-03 13:00:00',
    ]);

    $conversation = Conversation::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $newCustomer->getKey(),
    ]);
    Message::query()->forceCreate([
        'business_id' => $business->getKey(),
        'conversation_id' => $conversation->getKey(),
        'direction' => 'outbound',
        'type' => 'text',
        'status' => 'delivered',
        'sent_at' => '2026-11-01 12:00:00',
        'delivered_at' => '2026-11-01 12:01:00',
        'created_at' => '2026-11-01 12:00:00',
        'updated_at' => '2026-11-01 12:01:00',
    ]);
    Message::query()->forceCreate([
        'business_id' => $business->getKey(),
        'conversation_id' => $conversation->getKey(),
        'direction' => 'outbound',
        'type' => 'text',
        'status' => 'failed',
        'sent_at' => '2026-11-02 12:00:00',
        'failed_at' => '2026-11-02 12:01:00',
        'created_at' => '2026-11-02 12:00:00',
        'updated_at' => '2026-11-02 12:01:00',
    ]);
    ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $newCustomer->getKey(),
        'idempotency_key' => 'analytics-test-follow-up',
        'scheduled_at' => '2026-11-03 12:00:00',
    ]);
    Review::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $newCustomer->getKey(),
        'rating' => 4,
        'reviewed_at' => '2026-11-03 12:00:00',
    ]);

    $this->actingAs($viewer)->get('/analytics?start_date=2026-11-01&end_date=2026-11-03')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('analytics/index')
            ->where('report.customers_registered', 1)
            ->where('report.visits_recorded', 4)
            ->where('report.returning_customers', 3)
            ->where('report.messages_sent', 2)
            ->where('report.messages_delivered', 1)
            ->where('report.messages_failed', 1)
            ->where('report.follow_ups_scheduled', 1)
            ->where('report.reviews_received', 1)
            ->where('report.average_rating', 4)
            ->where('canExport', false)
            ->where('filters.start_date', '2026-11-01'));
});

it('requires analytics view and export permissions independently', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $viewer = User::factory()->create();
    $manager = User::factory()->create();
    confirmAnalyticsMfa($manager);
    $business->members()->attach($viewer, ['role' => 'barber', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($manager, ['role' => 'manager', 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($viewer)->get('/analytics')->assertForbidden();
    $this->actingAs($manager)->get('/analytics')->assertOk();
    $this->actingAs($manager)->get('/analytics/export')->assertForbidden();
});

it('rejects analytics ranges longer than 366 days and exports aggregate-only values', function (): void {
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    confirmAnalyticsMfa($owner);
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($owner)->get('/analytics?start_date=2025-01-01&end_date=2026-01-02')
        ->assertSessionHasErrors('end_date');

    $response = $this->actingAs($owner)->get('/analytics/export?start_date=2026-11-01&end_date=2026-11-02');
    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('customers_registered')
        ->not->toContain('phone')
        ->not->toContain('first_name');
});

function confirmAnalyticsMfa(User $user): void
{
    $user->forceFill([
        'two_factor_secret' => encrypt('analytics-test-secret'),
        'two_factor_confirmed_at' => now(),
    ])->save();
}

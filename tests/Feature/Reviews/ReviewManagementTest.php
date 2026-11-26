<?php

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reviewManager(Business $business, array $permissions): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $slug = 'review-manager-'.str()->random(8);
    Role::query()->create(['business_id' => $business->getKey(), 'name' => 'Review manager', 'slug' => $slug, 'permissions' => $permissions]);
    $business->members()->attach($user, ['role' => $slug, 'status' => 'active', 'joined_at' => now()]);

    return $user;
}

it('updates customer feedback through CQRS with a minimal audit record', function (): void {
    $business = Business::factory()->create();
    $user = reviewManager($business, ['reviews.view', 'reviews.update']);
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $review = Review::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(),
        'rating' => 2, 'body' => 'Could be better', 'source' => 'internal', 'reviewed_at' => now()->subDay(),
    ]);

    $this->actingAs($user)->put('/reviews/'.$review->getKey(), ['rating' => 4, 'body' => 'The customer clarified the feedback.'])->assertRedirect();

    expect($review->fresh()->rating)->toBe(4)
        ->and($review->fresh()->body)->toBe('The customer clarified the feedback.');
    $this->assertDatabaseHas('audit_logs', [
        'business_id' => $business->getKey(), 'user_id' => $user->getKey(),
        'action' => 'review.updated', 'auditable_id' => $review->getKey(),
    ]);
    $audit = AuditLog::query()->where('action', 'review.updated')->firstOrFail();
    expect($audit->before)->toBe(['rating' => 2])
        ->and($audit->after)->toBe(['rating' => 4, 'body_changed' => true]);
});

it('enforces review edit permission and single-business scope', function (): void {
    $business = Business::factory()->create();
    $user = reviewManager($business, ['reviews.view']);
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $review = Review::query()->create(['business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'rating' => 3, 'source' => 'internal', 'reviewed_at' => now()]);
    $foreignBusiness = Business::factory()->create(['is_active' => false]);
    $foreignCustomer = Customer::factory()->create(['business_id' => $foreignBusiness->getKey()]);
    $foreignReview = Review::query()->create(['business_id' => $foreignBusiness->getKey(), 'customer_id' => $foreignCustomer->getKey(), 'rating' => 3, 'source' => 'internal', 'reviewed_at' => now()]);

    $this->actingAs($user)->put('/reviews/'.$review->getKey(), ['rating' => 4])->assertForbidden();
    $modifier = reviewManager($business, ['reviews.view', 'reviews.update']);
    $this->actingAs($modifier)->put('/reviews/'.$foreignReview->getKey(), ['rating' => 4])->assertNotFound();

    expect($review->fresh()->rating)->toBe(3)
        ->and($foreignReview->fresh()->rating)->toBe(3);
});

it('exports authorized review records as formula-safe private csv and audits the export', function (): void {
    $business = Business::factory()->create();
    $user = reviewManager($business, ['reviews.view', 'reviews.export']);
    $customer = Customer::factory()->create(['business_id' => $business->getKey(), 'first_name' => 'Ada']);
    Review::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(),
        'rating' => 5, 'body' => '=HYPERLINK("https://example.test")', 'source' => 'whatsapp', 'reviewed_at' => now(),
    ]);
    $otherBusiness = Business::factory()->create(['is_active' => false]);
    $otherCustomer = Customer::factory()->create(['business_id' => $otherBusiness->getKey(), 'first_name' => 'Hidden']);
    Review::query()->create([
        'business_id' => $otherBusiness->getKey(), 'customer_id' => $otherCustomer->getKey(),
        'rating' => 1, 'body' => 'foreign feedback must not export', 'source' => 'internal', 'reviewed_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/reviews/export');
    $response->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $csv = $response->streamedContent();

    expect($csv)->toContain('Ada')->toContain("'=HYPERLINK")
        ->and($csv)->toContain('WhatsApp phone')
        ->and($csv)->not->toContain('foreign feedback must not export');
    $this->assertDatabaseHas('audit_logs', ['business_id' => $business->getKey(), 'user_id' => $user->getKey(), 'action' => 'reviews.exported']);
});

it('hides creation and export actions when their permissions are missing', function (): void {
    $business = Business::factory()->create();
    $user = reviewManager($business, ['reviews.view']);

    $this->actingAs($user)->get('/reviews')->assertOk()->assertInertia(fn ($page) => $page
        ->where('permissions.create', false)
        ->where('permissions.update', false)
        ->where('permissions.export', false)
        ->has('customers', 0)
    );
    $this->get('/reviews/export')->assertForbidden();
});

it('provides only minimal customer options to authorized review creators', function (): void {
    $business = Business::factory()->create();
    $user = reviewManager($business, ['reviews.view', 'reviews.create', 'customers.view']);
    Customer::factory()->create(['business_id' => $business->getKey(), 'first_name' => 'Amina', 'phone' => '+2348012345678', 'email' => 'private@example.test']);

    $response = $this->actingAs($user)->get('/reviews');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('permissions.create', true)
        ->has('customers', 1)
        ->where('customers.0.first_name', 'Amina')
        ->missing('customers.0.phone')
        ->missing('customers.0.email')
    );
    expect($response->getContent())->not->toContain('+2348012345678')->not->toContain('private@example.test');
});

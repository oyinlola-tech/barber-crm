<?php

use App\Models\Business;
use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Models\Visit;
use App\Services\Reviews\Commands\RecordReviewCommand;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('rejects attaching a customer review to another customers visit', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $user->forceFill([
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['test-recovery-code'])),
        'two_factor_confirmed_at' => now(),
    ])->save();
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active']);
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $otherCustomer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $visit = Visit::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $otherCustomer->getKey(),
        'visited_at' => now()->subDay(),
        'recorded_by' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->withSession(['current_business_id' => $business->getKey()])
        ->post(route('reviews.store'), [
            'customer_id' => $customer->getKey(),
            'visit_id' => $visit->getKey(),
            'rating' => 5,
        ])
        ->assertSessionHasErrors('visit_id');

    $this->assertDatabaseCount('reviews', 0);
});

it('cancels queued review-request follow-ups after a review without cancelling other or in-flight messages', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $visit = Visit::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'visited_at' => now()->subDay(),
        'recorded_by' => $user->getKey(),
    ]);
    $reviewTemplate = MessageTemplate::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'review_request',
        'language' => 'en',
        'purpose' => 'review_request',
    ]);
    $thankYouTemplate = MessageTemplate::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'thank_you',
        'language' => 'en',
        'purpose' => 'thank_you',
    ]);
    $scheduledMessages = [];
    foreach ([
        'review_pending' => ['pending', $reviewTemplate],
        'review_queued' => ['queued', $reviewTemplate],
        'review_sending' => ['sending', $reviewTemplate],
        'review_uncertain' => ['reconciliation_required', $reviewTemplate],
        'thank_you_pending' => ['pending', $thankYouTemplate],
    ] as $key => [$status, $template]) {
        $scheduledMessages[$key] = ScheduledMessage::query()->create([
            'business_id' => $business->getKey(),
            'customer_id' => $customer->getKey(),
            'automation_rule_id' => null,
            'channel' => 'whatsapp',
            'idempotency_key' => 'review-stop-'.$key,
            'payload' => ['template_id' => $template->getKey()],
            'status' => $status,
            'scheduled_at' => now()->addDay(),
        ]);
    }

    app(RecordReviewCommand::class)->execute($business->getKey(), $user->getKey(), [
        'customer_id' => $customer->getKey(),
        'visit_id' => $visit->getKey(),
        'rating' => 5,
    ]);

    expect($scheduledMessages['review_pending']->fresh()->status)->toBe('cancelled')
        ->and($scheduledMessages['review_queued']->fresh()->status)->toBe('cancelled')
        ->and($scheduledMessages['review_sending']->fresh()->status)->toBe('sending')
        ->and($scheduledMessages['review_uncertain']->fresh()->status)->toBe('reconciliation_required')
        ->and($scheduledMessages['thank_you_pending']->fresh()->status)->toBe('pending');
});

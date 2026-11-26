<?php

use App\Jobs\Automations\ProcessScheduledMessageJob;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Role;
use App\Models\ScheduledMessage;
use App\Models\User;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createScheduledMessageForManagement(Business $business, string $status = 'pending'): ScheduledMessage
{
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);

    return ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'idempotency_key' => 'management:'.fake()->uuid(),
        'payload' => ['template_id' => 1, 'template_name' => 'follow_up', 'language' => 'en'],
        'status' => $status,
        'scheduled_at' => now()->addDay(),
        'attempts' => 0,
    ]);
}

function authenticateForMessageManagement(Business $business, array $permissions = ['*']): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $permissions = $permissions === ['*'] ? ['messages.*'] : $permissions;
    $roleSlug = 'message-operator-'.str()->random(8);
    Role::query()->create(['business_id' => $business->getKey(), 'name' => 'Message operator', 'slug' => $roleSlug, 'permissions' => $permissions]);
    $business->members()->attach($user, ['role' => $roleSlug, 'status' => 'active', 'joined_at' => now()]);

    return $user;
}

beforeEach(function (): void {
    $this->seed(BusinessRoleSeeder::class);
});

it('shows only messages for the configured business and exposes action capabilities', function (): void {
    $business = Business::factory()->create();
    $user = authenticateForMessageManagement($business);
    $visible = createScheduledMessageForManagement($business);
    $otherBusiness = Business::factory()->create();
    $otherBusiness->update(['is_active' => false]);
    $hidden = createScheduledMessageForManagement($otherBusiness);

    $this->actingAs($user)->get('/messages/scheduled')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('messages/scheduled')
        ->where('permissions.cancel', true)
        ->where('permissions.retry', true)
        ->has('messages.data', 1)
        ->where('messages.data.0.id', $visible->getKey())
        ->where('messages.data.0.customer.phone', $visible->customer->phone)
        ->missing('messages.data.0.customer.email')
        ->missing('messages.data.1.id')
    );

    expect($hidden->business_id)->not->toBe($visible->business_id);
});

it('cancels only pending and queued messages and records the actor', function (): void {
    $business = Business::factory()->create();
    $user = authenticateForMessageManagement($business);
    $message = createScheduledMessageForManagement($business, 'queued');

    $this->actingAs($user)->post('/messages/scheduled/'.$message->getKey().'/cancel')->assertRedirect();

    expect($message->fresh()->status)->toBe('cancelled')
        ->and($message->fresh()->claimed_at)->toBeNull();
    $this->assertDatabaseHas('audit_logs', [
        'business_id' => $business->getKey(),
        'user_id' => $user->getKey(),
        'action' => 'scheduled_message.cancelled',
        'auditable_id' => $message->getKey(),
    ]);
});

it('rejects cancellation once a message is sending and rejects cross-business access', function (): void {
    $business = Business::factory()->create();
    $user = authenticateForMessageManagement($business);
    $sending = createScheduledMessageForManagement($business, 'sending');
    $foreignBusiness = Business::factory()->create();
    $foreignBusiness->update(['is_active' => false]);
    $foreign = createScheduledMessageForManagement($foreignBusiness);

    $this->actingAs($user)->post('/messages/scheduled/'.$sending->getKey().'/cancel')->assertSessionHasErrors('message');
    $this->post('/messages/scheduled/'.$foreign->getKey().'/cancel')->assertNotFound();

    expect($sending->fresh()->status)->toBe('sending');
});

it('queues a bounded retry only for definitively failed messages', function (): void {
    Queue::fake();
    config(['automation.manual_retry_limit' => 2]);
    $business = Business::factory()->create();
    $user = authenticateForMessageManagement($business);
    $message = createScheduledMessageForManagement($business, 'failed');
    $message->update(['last_error' => 'provider_rejected_http_400', 'failed_at' => now()]);

    $this->actingAs($user)->post('/messages/scheduled/'.$message->getKey().'/retry')->assertRedirect();

    expect($message->fresh()->status)->toBe('queued')
        ->and($message->fresh()->payload['manual_retry_count'])->toBe(1)
        ->and($message->fresh()->last_error)->toBeNull();
    Queue::assertPushed(ProcessScheduledMessageJob::class, fn ($job): bool => $job->scheduledMessageId === $message->getKey());
    $this->assertDatabaseHas('audit_logs', ['user_id' => $user->getKey(), 'action' => 'scheduled_message.retry_queued', 'auditable_id' => $message->getKey()]);
});

it('refuses retries of uncertain delivery and enforces permissions at the server', function (): void {
    $business = Business::factory()->create();
    $operator = authenticateForMessageManagement($business, ['messages.view']);
    $failed = createScheduledMessageForManagement($business, 'failed');
    $uncertain = createScheduledMessageForManagement($business, 'reconciliation_required');

    $this->actingAs($operator)->post('/messages/scheduled/'.$failed->getKey().'/retry')->assertForbidden();
    $this->post('/messages/scheduled/'.$uncertain->getKey().'/retry')->assertForbidden();
    $this->get('/messages/scheduled')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('permissions.cancel', false)
        ->where('permissions.retry', false)
    );

    expect($failed->fresh()->status)->toBe('failed')
        ->and($uncertain->fresh()->status)->toBe('reconciliation_required');
});

it('enforces the configured limit for manual retries', function (): void {
    Queue::fake();
    config(['automation.manual_retry_limit' => 1]);
    $business = Business::factory()->create();
    $user = authenticateForMessageManagement($business);
    $message = createScheduledMessageForManagement($business, 'failed');
    $message->update(['payload' => ['manual_retry_count' => 1], 'last_error' => 'provider_rejected_http_400']);

    $this->actingAs($user)->post('/messages/scheduled/'.$message->getKey().'/retry')->assertSessionHasErrors('message');

    expect($message->fresh()->status)->toBe('failed');
    Queue::assertNothingPushed();
});

<?php

use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationNote;
use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppConnection;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function makeInboxConversation(bool $withConsent = true, bool $withinServiceWindow = true): array
{
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey(), 'phone' => '+2348012345678']);
    $user = User::factory()->create();
    $user->forceFill([
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['test-recovery-code'])),
        'two_factor_confirmed_at' => now(),
    ])->save();
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $conversation = Conversation::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'status' => 'open',
    ]);
    $conversation->forceFill([
        'unread_count' => 1,
        'last_customer_message_at' => now(),
    ])->save();

    if ($withConsent) {
        CustomerConsent::query()->create([
            'business_id' => $business->getKey(),
            'customer_id' => $customer->getKey(),
            'channel' => 'whatsapp',
            'granted' => true,
            'source' => 'in_person',
            'recorded_at' => now()->subDay(),
        ]);
    }

    Message::query()->create([
        'business_id' => $business->getKey(),
        'conversation_id' => $conversation->getKey(),
        'provider_message_id' => 'wamid.inbound',
        'direction' => 'inbound',
        'type' => 'text',
        'status' => 'received',
        'body' => 'Hello, I have a question.',
        'sent_at' => $withinServiceWindow ? now()->subHours(2) : now()->subHours(25),
    ]);

    WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(),
        'phone_number_id' => 'phone-number-1',
        'access_token' => 'test-access-token',
        'status' => 'connected',
    ]);

    config(['whatsapp.api_version' => 'v99.0']);

    return [$user, $business, $customer, $conversation];
}

it('renders customer conversations and inbox details from persisted data', function (): void {
    [$user, , , $conversation] = makeInboxConversation();

    $this->actingAs($user)->get('/inbox')->assertInertia(fn (Assert $page) => $page
        ->component('inbox/index')
        ->has('conversations.data', 1)
        ->where('conversations.data.0.id', $conversation->getKey()));

    $this->actingAs($user)->get('/inbox/'.$conversation->getKey())->assertInertia(fn (Assert $page) => $page
        ->component('inbox/show')
        ->where('conversation.id', $conversation->getKey())
        ->where('canReply', true)
        ->where('consentGranted', true)
        ->where('serviceWindowOpen', true)
        ->has('conversation.messages', 1));
});

it('sends a free-form reply only with consent and within the customer service window', function (): void {
    [$user, , , $conversation] = makeInboxConversation();
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.outbound']]], 200)]);

    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/reply', ['body' => 'Thanks for reaching out.'])
        ->assertRedirect();

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->getKey(),
        'provider_message_id' => 'wamid.outbound',
        'direction' => 'outbound',
        'status' => 'sent',
    ]);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/phone-number-1/messages')
        && $request['text']['body'] === 'Thanks for reaching out.'
        && $request->hasHeader('Authorization', 'Bearer test-access-token'));
});

it('records uncertain provider acceptance without automatically retrying the reply', function (): void {
    [$user, , , $conversation] = makeInboxConversation();
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Temporary provider error']], 500)]);

    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/reply', ['body' => 'Please let us know if you need help.'])
        ->assertRedirect();

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->getKey(),
        'direction' => 'outbound',
        'status' => 'delivery_uncertain',
    ]);
    $this->assertDatabaseHas('message_events', [
        'business_id' => $conversation->business_id,
        'event_type' => 'message.send_uncertain',
        'status' => 'delivery_uncertain',
    ]);
    Http::assertSentCount(1);
});

it('rejects manual replies without active consent or after the service window closes', function (): void {
    [$user, , , $conversation] = makeInboxConversation(withConsent: false);
    Http::fake();

    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/reply', ['body' => 'A reply'])
        ->assertSessionHasErrors('body');
    Http::assertNothingSent();

    CustomerConsent::query()->create([
        'business_id' => $conversation->business_id,
        'customer_id' => $conversation->customer_id,
        'channel' => 'whatsapp',
        'granted' => true,
        'source' => 'in_person',
        'recorded_at' => now(),
    ]);
    $conversation->messages()->where('direction', 'inbound')->update(['sent_at' => now()->subHours(25)]);
    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/reply', ['body' => 'A reply'])
        ->assertSessionHasErrors('body');
    Http::assertNothingSent();
});

it('does not expose an inbox to employees without conversation permission', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    [$user, $business] = makeInboxConversation();
    $viewer = User::factory()->create();
    $business->members()->attach($viewer, ['role' => 'viewer', 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($viewer)->get('/inbox')->assertForbidden();
    $this->actingAs($viewer)->post('/inbox/1/reply', ['body' => 'Unauthorized'])->assertForbidden();
});

it('does not serialize internal notes for staff with read-only conversation access', function (): void {
    [$user, $business, , $conversation] = makeInboxConversation();
    ConversationNote::query()->create([
        'business_id' => $business->getKey(),
        'conversation_id' => $conversation->getKey(),
        'created_by' => $user->getKey(),
        'body' => 'Private staff note.',
    ]);
    $observerRole = Role::query()->create([
        'business_id' => null,
        'name' => 'Inbox observer',
        'slug' => 'inbox_observer',
        'permissions' => ['conversations.view'],
    ]);
    $observer = User::factory()->create();
    $business->members()->attach($observer, ['role' => $observerRole->slug, 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($observer)->get('/inbox/'.$conversation->getKey())->assertInertia(fn (Assert $page) => $page
        ->component('inbox/show')
        ->where('canReply', false)
        ->where('canAddNote', false)
        ->missing('conversation.notes'));
});

it('supports assignment, private notes, closing and marking conversations read', function (): void {
    [$user, $business, , $conversation] = makeInboxConversation();
    $assignee = User::factory()->create();
    $business->members()->attach($assignee, ['role' => 'barber', 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/assignment', ['assignee_id' => $assignee->getKey()])->assertRedirect();
    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/notes', ['body' => 'Customer prefers a low fade.'])->assertRedirect();
    $this->actingAs($user)->patch('/inbox/'.$conversation->getKey().'/status', ['status' => 'closed'])->assertRedirect();
    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/read')->assertRedirect();

    expect($conversation->fresh()->assigned_to)->toBe($assignee->getKey())
        ->and($conversation->fresh()->status)->toBe('closed')
        ->and($conversation->fresh()->unread_count)->toBe(0);
    $this->assertDatabaseHas('conversation_notes', ['conversation_id' => $conversation->getKey(), 'body' => 'Customer prefers a low fade.']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'conversation.assigned']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'conversation.status_changed']);
});

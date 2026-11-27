<?php

use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationNote;
use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppConnection;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

function makeApprovedInboxTemplate(int $businessId): MessageTemplate
{
    return MessageTemplate::query()->create([
        'business_id' => $businessId,
        'provider_template_id' => 'meta-template-1',
        'name' => 'visit_follow_up',
        'language' => 'en',
        'category' => 'UTILITY',
        'purpose' => 'experience',
        'status' => 'approved',
        'provider_status' => 'APPROVED',
        'is_enabled' => true,
        'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}, thank you for visiting.']],
        'variable_map' => ['customer_name'],
    ]);
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
        ->where('assignees.0.id', $user->getKey())
        ->where('canReply', true)
        ->where('consentGranted', true)
        ->where('serviceWindowOpen', true)
        ->has('conversation.messages', 1));
});

it('does not expose staff assignment data to conversation viewers without assignment permission', function (): void {
    [$owner, $business, , $conversation] = makeInboxConversation();
    $staff = User::factory()->create(['name' => 'Private staff identity']);
    $business->members()->attach($staff, ['role' => 'barber', 'status' => 'active', 'joined_at' => now()]);
    $viewer = User::factory()->create();
    Role::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Conversation reader',
        'slug' => 'conversation_reader',
        'permissions' => ['conversations.view'],
    ]);
    $business->members()->attach($viewer, ['role' => 'conversation_reader', 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($viewer)->get('/inbox/'.$conversation->getKey())->assertInertia(fn (Assert $page) => $page
        ->where('canAssign', false)
        ->has('assignees', 0)
        ->missing('assignees.0.name'));

    expect($owner->hasBusinessPermission((int) $business->getKey(), 'conversations.assign'))->toBeTrue();
});

it('sends a free-form reply only with consent and within the customer service window', function (): void {
    [$user, , , $conversation] = makeInboxConversation();
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.outbound']]], 200)]);

    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/reply', ['body' => 'Thanks for reaching out.', 'idempotency_key' => '8a9f6ac6-d2a4-4c02-93c8-b3ce3e4355e7'])
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

it('reuses successful free-form replies for the same idempotency key and rejects key reuse', function (): void {
    [$user, , , $conversation] = makeInboxConversation();
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.outbound.once']]], 200)]);
    $payload = [
        'body' => 'Thanks for reaching out.',
        'idempotency_key' => 'bfaec48f-965d-4bce-94b8-a697d6d2a8c7',
    ];

    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/reply', $payload)->assertRedirect();
    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/reply', $payload)->assertRedirect();
    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/reply', [
        ...$payload,
        'body' => 'A different reply.',
    ])->assertSessionHasErrors('idempotency_key');

    Http::assertSentCount(1);
    $this->assertDatabaseCount('messages', 2);
});

it('blocks another free-form reply after uncertain provider acceptance until a new customer message arrives', function (): void {
    [$user, , , $conversation] = makeInboxConversation();
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Temporary provider error']], 500)]);

    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/reply', ['body' => 'Please let us know if you need help.', 'idempotency_key' => '3a0dbcb7-0c61-4aef-9d20-5984538db411'])
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

    $this->actingAs($user)->get('/inbox/'.$conversation->getKey())->assertInertia(fn (Assert $page) => $page
        ->where('freeFormReplyBlocked', true)
        ->where('canReply', false));
    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/reply', ['body' => 'A duplicate reply.', 'idempotency_key' => '62fcbf79-3ed8-4dde-878d-3f550b6b1ae9'])
        ->assertSessionHasErrors('body');

    $this->assertDatabaseCount('messages', 2);
    Http::assertSentCount(1);
});

it('blocks a second free-form reply while the previous provider request is still pending', function (): void {
    [$user, , , $conversation] = makeInboxConversation();
    Message::query()->create([
        'business_id' => $conversation->business_id,
        'conversation_id' => $conversation->getKey(),
        'direction' => 'outbound',
        'type' => 'text',
        'status' => 'pending',
        'body' => 'First reply still processing.',
    ]);
    Http::fake();

    $this->actingAs($user)->get('/inbox/'.$conversation->getKey())->assertInertia(fn (Assert $page) => $page
        ->where('freeFormReplyBlocked', true)
        ->where('canReply', false));
    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/reply', ['body' => 'A duplicate reply.', 'idempotency_key' => 'e36d5c1b-f11a-44d1-94b2-338095a0f27b'])
        ->assertSessionHasErrors('body');

    $this->assertDatabaseCount('messages', 2);
    Http::assertNothingSent();
});

it('records a definite failure when the saved WhatsApp token cannot be decrypted', function (): void {
    [$user, , , $conversation] = makeInboxConversation();
    $connection = WhatsAppConnection::query()->where('business_id', $conversation->business_id)->firstOrFail();
    DB::table('whatsapp_connections')->where('id', $connection->getKey())->update(['access_token' => 'invalid-encrypted-token']);
    Http::fake();

    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/reply', ['body' => 'Please let us know if you need help.', 'idempotency_key' => '7a6246b0-83c6-45e6-aacf-20f98b36a0de'])
        ->assertRedirect()
        ->assertSessionHas('status', 'WhatsApp did not send the reply. Check the connection and message status before trying again.');

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->getKey(),
        'direction' => 'outbound',
        'status' => 'failed',
    ]);
    $this->assertDatabaseHas('message_events', [
        'business_id' => $conversation->business_id,
        'event_type' => 'message.send_failed',
        'status' => 'failed',
    ]);
    Http::assertNothingSent();
});

it('sends approved mapped templates when the 24-hour service window is closed', function (): void {
    [$user, $business, , $conversation] = makeInboxConversation(withinServiceWindow: false);
    $template = makeApprovedInboxTemplate((int) $business->getKey());
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.template']]], 200)]);

    $this->actingAs($user)->get('/inbox/'.$conversation->getKey())->assertInertia(fn (Assert $page) => $page
        ->where('canSendTemplate', true)
        ->where('templates.0.id', $template->getKey())
        ->missing('templates.0.provider_template_id'));

    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/template-reply', [
        'template_id' => $template->getKey(),
        'idempotency_key' => '7fd47f00-cac2-4c64-a154-5c0a2797745b',
    ])->assertRedirect();

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->getKey(),
        'provider_message_id' => 'wamid.template',
        'direction' => 'outbound',
        'type' => 'template',
        'status' => 'accepted',
        'body' => 'Hello '.trim($conversation->customer->first_name.' '.$conversation->customer->last_name).', thank you for visiting.',
    ]);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/phone-number-1/messages')
        && $request['template']['name'] === 'visit_follow_up'
        && $request['template']['components'][0]['parameters'][0]['text'] === trim($conversation->customer->first_name.' '.$conversation->customer->last_name));
});

it('requires active WhatsApp consent and an approved enabled template for template replies', function (): void {
    [$user, $business, , $conversation] = makeInboxConversation(withConsent: false, withinServiceWindow: false);
    $template = makeApprovedInboxTemplate((int) $business->getKey());
    Http::fake();

    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/template-reply', [
        'template_id' => $template->getKey(),
        'idempotency_key' => '848c79a6-13a3-4c1a-b4b2-15a32d4ae834',
    ])->assertSessionHasErrors('template_id');

    CustomerConsent::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $conversation->customer_id,
        'channel' => 'whatsapp',
        'granted' => true,
        'source' => 'in_person',
        'recorded_at' => now(),
    ]);
    $template->update(['provider_status' => 'REJECTED']);
    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/template-reply', [
        'template_id' => $template->getKey(),
        'idempotency_key' => 'dc4429a3-3e1d-43bc-9f9d-4977e82de1e8',
    ])->assertSessionHasErrors('template_id');

    Http::assertNothingSent();
    $this->assertDatabaseCount('messages', 1);
});

it('uses template reply idempotency keys to prevent duplicate provider sends', function (): void {
    [$user, $business, , $conversation] = makeInboxConversation(withinServiceWindow: false);
    $template = makeApprovedInboxTemplate((int) $business->getKey());
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.template.once']]], 200)]);
    $payload = [
        'template_id' => $template->getKey(),
        'idempotency_key' => '55249292-7d86-47c7-9d34-cd5ab72b76c8',
    ];

    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/template-reply', $payload)->assertRedirect();
    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/template-reply', $payload)->assertRedirect();
    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/template-reply', [
        ...$payload,
        'template_id' => $template->getKey() + 1,
    ])->assertSessionHasErrors('idempotency_key');

    Http::assertSentCount(1);
    $this->assertDatabaseCount('messages', 2);
    $this->assertDatabaseHas('messages', ['idempotency_key' => $payload['idempotency_key'], 'status' => 'accepted']);
});

it('does not retry template sends after an ambiguous provider response', function (): void {
    [$user, $business, , $conversation] = makeInboxConversation(withinServiceWindow: false);
    $template = makeApprovedInboxTemplate((int) $business->getKey());
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Temporary provider error']], 500)]);
    $payload = [
        'template_id' => $template->getKey(),
        'idempotency_key' => 'becf9a17-21df-46ea-b940-f18ab9ab1c14',
    ];

    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/template-reply', $payload)
        ->assertRedirect()
        ->assertSessionHas('status', 'WhatsApp delivery is uncertain. Do not resend until the message is reconciled.');
    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/template-reply', $payload)->assertRedirect();
    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/template-reply', [
        ...$payload,
        'idempotency_key' => 'd88b73da-3dc0-4206-831b-a1dd04441833',
    ])->assertSessionHasErrors('template_id');

    Http::assertSentCount(1);
    $this->assertDatabaseHas('messages', ['idempotency_key' => $payload['idempotency_key'], 'status' => 'delivery_uncertain']);
    $this->actingAs($user)->get('/inbox/'.$conversation->getKey())->assertInertia(fn (Assert $page) => $page
        ->where('templateSendBlocked', true));
});

it('rejects manual replies without active consent or after the service window closes', function (): void {
    [$user, , , $conversation] = makeInboxConversation(withConsent: false);
    Http::fake();

    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/reply', ['body' => 'A reply', 'idempotency_key' => '58548aa4-59b9-46d1-9fdd-0e137f350f45'])
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
    $this->actingAs($user)->post('/inbox/'.$conversation->getKey().'/reply', ['body' => 'A reply', 'idempotency_key' => 'ebc3ba8e-c835-439a-9ff3-d136783facd4'])
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
    $this->actingAs($viewer)->post('/inbox/1/template-reply', [
        'template_id' => 1,
        'idempotency_key' => '3a85f035-4ca0-4625-81f7-3606cb6e199c',
    ])->assertForbidden();
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

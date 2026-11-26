<?php

use App\Jobs\WhatsApp\ProcessWhatsAppWebhookJob;
use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Models\Message;
use App\Models\Role;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Models\WhatsAppConnection;
use App\Services\Automations\Commands\CancelCustomerFollowUpsCommand;
use App\Services\Consent\Commands\RecordConsentCommand;
use App\Services\Consent\Queries\CustomerCanReceiveMessageQuery;
use App\Services\Settings\Queries\GetWhatsAppMessagingSettingsQuery;
use App\Utils\PhoneNumberNormalizer;
use App\Validators\WhatsApp\ConnectWhatsAppRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

uses(LazilyRefreshDatabase::class);

it('rejects a webhook with an invalid signature', function (): void {
    Config::set('whatsapp.app_secret', 'test-app-secret');

    $this->postJson(route('webhooks.whatsapp.receive'), ['entry' => []])
        ->assertUnauthorized();

    $this->assertDatabaseCount('webhook_events', 0);
});

it('returns the challenge for a valid provider verification token', function (): void {
    config(['whatsapp.verify_token' => 'verification-token']);

    $this->get(route('webhooks.whatsapp.verify').'?hub.mode=subscribe&hub.verify_token=verification-token&hub.challenge=challenge-123')
        ->assertOk()
        ->assertSeeText('challenge-123');
});

it('stores a signed inbound event once and queues it for processing', function (): void {
    Queue::fake([ProcessWhatsAppWebhookJob::class]);
    Config::set('whatsapp.app_secret', 'test-app-secret');
    $business = Business::factory()->create();
    WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(),
        'phone_number_id' => 'phone-number-1',
        'business_account_id' => 'business-account-1',
        'access_token' => 'secret-token',
        'status' => 'connected',
    ]);
    $payload = [
        'entry' => [[
            'id' => 'business-account-1',
            'changes' => [[
                'value' => [
                    'metadata' => ['phone_number_id' => 'phone-number-1'],
                    'messages' => [['id' => 'wamid.123', 'from' => '+2348012345678', 'type' => 'text', 'text' => ['body' => 'Hello']]],
                ],
            ]],
        ]],
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'test-app-secret');

    $this->call('POST', route('webhooks.whatsapp.receive'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body)->assertOk();
    $this->call('POST', route('webhooks.whatsapp.receive'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body)->assertOk();

    $this->assertDatabaseCount('webhook_events', 1);
    Queue::assertPushedTimes(ProcessWhatsAppWebhookJob::class, 1);
});

it('rejects oversized raw webhook payloads before decoding', function (): void {
    Config::set('whatsapp.app_secret', 'test-app-secret');
    Config::set('whatsapp.webhook_max_bytes', 1024);
    $body = str_repeat('x', 1025);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'test-app-secret');

    $this->call('POST', route('webhooks.whatsapp.receive'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body)->assertStatus(413);

    $this->assertDatabaseCount('webhook_events', 0);
});

it('rejects a correctly signed malformed JSON body', function (): void {
    Config::set('whatsapp.app_secret', 'test-app-secret');
    $body = '{"entry":[';
    $signature = 'sha256='.hash_hmac('sha256', $body, 'test-app-secret');

    $this->call('POST', route('webhooks.whatsapp.receive'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body)->assertBadRequest();

    $this->assertDatabaseCount('webhook_events', 0);
});

it('does not queue a webhook addressed to a different WhatsApp business account', function (): void {
    Queue::fake([ProcessWhatsAppWebhookJob::class]);
    Config::set('whatsapp.app_secret', 'test-app-secret');
    $business = Business::factory()->create();
    WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(),
        'phone_number_id' => 'phone-number-1',
        'business_account_id' => 'account-right',
        'access_token' => 'secret-token',
        'status' => 'connected',
    ]);
    $payload = ['entry' => [[
        'id' => 'account-wrong',
        'changes' => [['value' => [
            'metadata' => ['phone_number_id' => 'phone-number-1'],
            'messages' => [['id' => 'wamid.foreign', 'from' => '2348012345678', 'type' => 'text']],
        ]]],
    ]]];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'test-app-secret');

    $this->call('POST', route('webhooks.whatsapp.receive'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body)->assertOk();

    $this->assertDatabaseHas('webhook_events', ['event_id' => 'message:wamid.foreign', 'status' => 'unmatched_connection']);
    Queue::assertNothingPushed();
});

it('requires the WhatsApp business account id when saving a connection', function (): void {
    $validator = validator(
        ['phone_number_id' => 'phone-number-1', 'access_token' => 'test-token'],
        (new ConnectWhatsAppRequest)->rules(),
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('business_account_id'))->toBeTrue();
});

it('stores explicit button replies as message text for deterministic opt-out handling', function (): void {
    Queue::fake([ProcessWhatsAppWebhookJob::class]);
    Config::set('whatsapp.app_secret', 'test-app-secret');
    $business = Business::factory()->create();
    WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(),
        'phone_number_id' => 'phone-number-1',
        'business_account_id' => 'business-account-1',
        'access_token' => 'secret-token',
        'status' => 'connected',
    ]);
    $payload = ['object' => 'whatsapp_business_account', 'entry' => [[
        'id' => 'business-account-1',
        'changes' => [['value' => [
            'metadata' => ['phone_number_id' => 'phone-number-1'],
            'messages' => [[
                'id' => 'wamid.button-stop',
                'from' => '2348012345678',
                'type' => 'button',
                'button' => ['text' => 'STOP'],
            ]],
        ]]],
    ]]];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'test-app-secret');

    $this->call('POST', route('webhooks.whatsapp.receive'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body)->assertOk();

    expect(WebhookEvent::query()->where('event_id', 'message:wamid.button-stop')->value('payload'))
        ->toMatchArray(['type' => 'button', 'body' => 'STOP']);
});

it('uses the configured exact opt-out keywords while processing inbound messages once', function (): void {
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey(), 'phone' => '+2348012345678']);
    BusinessSetting::query()->create([
        'business_id' => $business->getKey(),
        'key' => 'whatsapp.opt_out_keywords',
        'value' => ['NO MORE'],
    ]);
    CustomerConsent::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp',
        'granted' => true, 'recorded_at' => now()->subDay(), 'source' => 'in_person',
    ]);
    $scheduled = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp',
        'idempotency_key' => 'opt-out-cancel-test', 'status' => 'pending', 'scheduled_at' => now()->addDay(),
    ]);
    $event = WebhookEvent::query()->create([
        'business_id' => $business->getKey(), 'provider' => 'whatsapp', 'event_id' => 'message:wamid.stop',
        'event_type' => 'message', 'payload' => [
            'kind' => 'message', 'phone_number_id' => 'phone-number-1', 'provider_message_id' => 'wamid.stop',
            'from' => '2348012345678', 'type' => 'text', 'body' => 'NO MORE!!!', 'timestamp' => now()->timestamp,
        ], 'status' => 'received',
    ]);
    WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(), 'phone_number_id' => 'phone-number-1', 'access_token' => 'secret-token', 'status' => 'connected',
    ]);
    $job = new ProcessWhatsAppWebhookJob((int) $event->getKey());

    $job->handle(app(PhoneNumberNormalizer::class), app(RecordConsentCommand::class), app(CancelCustomerFollowUpsCommand::class), app(GetWhatsAppMessagingSettingsQuery::class));
    $job->handle(app(PhoneNumberNormalizer::class), app(RecordConsentCommand::class), app(CancelCustomerFollowUpsCommand::class), app(GetWhatsAppMessagingSettingsQuery::class));

    $conversation = Conversation::query()->where('customer_id', $customer->getKey())->firstOrFail();
    expect($conversation->unread_count)->toBe(1)
        ->and($scheduled->fresh()->status)->toBe('cancelled')
        ->and(app(CustomerCanReceiveMessageQuery::class)->execute($business->getKey(), $customer->getKey(), 'whatsapp'))->toBeFalse();
    $this->assertDatabaseCount('messages', 1);
    $this->assertDatabaseHas('messages', ['provider_message_id' => 'wamid.stop', 'direction' => 'inbound', 'body' => 'NO MORE!!!']);
    $this->assertDatabaseHas('webhook_events', ['id' => $event->getKey(), 'status' => 'processed']);

    app(RecordConsentCommand::class)->execute((int) $business->getKey(), (int) $customer->getKey(), 'whatsapp', true, 'in_person');
    $defaultKeywordEvent = WebhookEvent::query()->create([
        'business_id' => $business->getKey(),
        'provider' => 'whatsapp',
        'event_id' => 'message:wamid.default-keyword',
        'event_type' => 'message',
        'payload' => [
            'kind' => 'message',
            'phone_number_id' => 'phone-number-1',
            'provider_message_id' => 'wamid.default-keyword',
            'from' => '2348012345678',
            'type' => 'text',
            'body' => 'STOP',
            'timestamp' => now()->timestamp,
        ],
        'status' => 'received',
    ]);
    (new ProcessWhatsAppWebhookJob((int) $defaultKeywordEvent->getKey()))->handle(
        app(PhoneNumberNormalizer::class),
        app(RecordConsentCommand::class),
        app(CancelCustomerFollowUpsCommand::class),
        app(GetWhatsAppMessagingSettingsQuery::class),
    );

    expect(app(CustomerCanReceiveMessageQuery::class)
        ->execute((int) $business->getKey(), (int) $customer->getKey(), 'whatsapp'))->toBeFalse();
});

it('notifies only active inbox staff once for a new inbound customer message without storing the message body', function (): void {
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey(), 'phone' => '+2348012345678']);
    $owner = User::factory()->create();
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $role = Role::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Inbox staff',
        'slug' => 'inbox-staff-'.str()->random(6),
        'permissions' => ['conversations.view'],
    ]);
    $inboxStaff = User::factory()->create();
    $business->members()->attach($inboxStaff, ['role' => $role->slug, 'status' => 'active', 'joined_at' => now()]);
    $viewerRole = Role::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'No inbox access',
        'slug' => 'no-inbox-'.str()->random(6),
        'permissions' => ['dashboard.view'],
    ]);
    $viewer = User::factory()->create();
    $business->members()->attach($viewer, ['role' => $viewerRole->slug, 'status' => 'active', 'joined_at' => now()]);
    $suspended = User::factory()->create();
    $business->members()->attach($suspended, ['role' => 'owner', 'status' => 'suspended', 'joined_at' => now()]);
    $event = WebhookEvent::query()->create([
        'business_id' => $business->getKey(),
        'provider' => 'whatsapp',
        'event_id' => 'message:wamid.staff-notification',
        'event_type' => 'message',
        'payload' => [
            'kind' => 'message', 'phone_number_id' => 'phone-number-1', 'provider_message_id' => 'wamid.staff-notification',
            'from' => '2348012345678', 'type' => 'text', 'body' => 'I need help with my last haircut', 'timestamp' => now()->timestamp,
        ],
        'status' => 'received',
    ]);
    WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(), 'phone_number_id' => 'phone-number-1', 'access_token' => 'secret-token', 'status' => 'connected',
    ]);
    $job = new ProcessWhatsAppWebhookJob((int) $event->getKey());

    $handle = fn () => $job->handle(
        app(PhoneNumberNormalizer::class),
        app(RecordConsentCommand::class),
        app(CancelCustomerFollowUpsCommand::class),
        app(GetWhatsAppMessagingSettingsQuery::class),
    );
    $handle();
    $handle();

    $this->assertDatabaseCount('notifications', 2);
    $conversationId = (int) Conversation::query()->where('customer_id', $customer->getKey())->value('id');
    expect($owner->notifications()->first()?->data)
        ->toMatchArray([
            'title' => 'New WhatsApp message',
            'conversation_id' => $conversationId,
            'url' => '/inbox/'.$conversationId,
        ])
        ->not->toHaveKey('message_body')
        ->not->toHaveKey('customer_phone');
    expect($inboxStaff->notifications()->count())->toBe(1);
    $this->assertDatabaseMissing('notifications', ['notifiable_id' => $viewer->getKey()]);
    $this->assertDatabaseMissing('notifications', ['notifiable_id' => $suspended->getKey()]);
});

it('stops only cancellable follow-ups when a customer sends a new inbound message', function (): void {
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey(), 'phone' => '+2348012345678']);
    $statuses = ['pending', 'queued', 'sending', 'reconciliation_required'];
    $scheduledMessages = [];
    foreach ($statuses as $status) {
        $scheduledMessages[$status] = ScheduledMessage::query()->create([
            'business_id' => $business->getKey(),
            'customer_id' => $customer->getKey(),
            'channel' => 'whatsapp',
            'idempotency_key' => 'inbound-stop-'.$status,
            'status' => $status,
            'scheduled_at' => now()->addDay(),
        ]);
    }
    $event = WebhookEvent::query()->create([
        'business_id' => $business->getKey(),
        'provider' => 'whatsapp',
        'event_id' => 'message:wamid.response-stop',
        'event_type' => 'message',
        'payload' => [
            'kind' => 'message',
            'phone_number_id' => 'phone-number-1',
            'provider_message_id' => 'wamid.response-stop',
            'from' => '2348012345678',
            'type' => 'text',
            'body' => 'I will come back next week',
            'timestamp' => now()->timestamp,
        ],
        'status' => 'received',
    ]);
    WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(),
        'phone_number_id' => 'phone-number-1',
        'access_token' => 'secret-token',
        'status' => 'connected',
    ]);

    (new ProcessWhatsAppWebhookJob((int) $event->getKey()))->handle(
        app(PhoneNumberNormalizer::class),
        app(RecordConsentCommand::class),
        app(CancelCustomerFollowUpsCommand::class),
        app(GetWhatsAppMessagingSettingsQuery::class),
    );

    expect($scheduledMessages['pending']->fresh()->status)->toBe('cancelled')
        ->and($scheduledMessages['queued']->fresh()->status)->toBe('cancelled')
        ->and($scheduledMessages['sending']->fresh()->status)->toBe('sending')
        ->and($scheduledMessages['reconciliation_required']->fresh()->status)->toBe('reconciliation_required');
});

it('applies delivery progress without allowing stale webhook statuses to regress it', function (): void {
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $conversation = Conversation::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp', 'status' => 'open',
    ]);
    $message = Message::query()->create([
        'business_id' => $business->getKey(), 'conversation_id' => $conversation->getKey(), 'provider_message_id' => 'wamid.outbound',
        'direction' => 'outbound', 'type' => 'text', 'status' => 'sent', 'body' => 'Thanks', 'sent_at' => now()->subMinutes(10),
    ]);
    WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(), 'phone_number_id' => 'phone-number-1', 'access_token' => 'secret-token', 'status' => 'connected',
    ]);
    $delivered = WebhookEvent::query()->create([
        'business_id' => $business->getKey(), 'provider' => 'whatsapp', 'event_id' => 'status:delivered-event',
        'event_type' => 'status', 'payload' => [
            'kind' => 'status', 'phone_number_id' => 'phone-number-1', 'provider_message_id' => 'wamid.outbound',
            'status' => 'delivered', 'timestamp' => now()->timestamp, 'error_code' => null,
        ], 'status' => 'received',
    ]);
    $sent = WebhookEvent::query()->create([
        'business_id' => $business->getKey(), 'provider' => 'whatsapp', 'event_id' => 'status:old-sent-event',
        'event_type' => 'status', 'payload' => [
            'kind' => 'status', 'phone_number_id' => 'phone-number-1', 'provider_message_id' => 'wamid.outbound',
            'status' => 'sent', 'timestamp' => now()->subMinutes(5)->timestamp, 'error_code' => null,
        ], 'status' => 'received',
    ]);

    (new ProcessWhatsAppWebhookJob((int) $delivered->getKey()))->handle(app(PhoneNumberNormalizer::class), app(RecordConsentCommand::class), app(CancelCustomerFollowUpsCommand::class), app(GetWhatsAppMessagingSettingsQuery::class));
    (new ProcessWhatsAppWebhookJob((int) $sent->getKey()))->handle(app(PhoneNumberNormalizer::class), app(RecordConsentCommand::class), app(CancelCustomerFollowUpsCommand::class), app(GetWhatsAppMessagingSettingsQuery::class));

    expect($message->fresh()->status)->toBe('delivered')->and($message->fresh()->delivered_at)->not->toBeNull();
});

it('records provider failure after sent but never regresses delivered or failed states', function (): void {
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $conversation = Conversation::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp', 'status' => 'open',
    ]);
    $message = Message::query()->create([
        'business_id' => $business->getKey(), 'conversation_id' => $conversation->getKey(), 'provider_message_id' => 'wamid.failed-after-sent',
        'direction' => 'outbound', 'type' => 'template', 'status' => 'sent', 'sent_at' => now()->subMinutes(10),
    ]);
    $deliveredMessage = Message::query()->create([
        'business_id' => $business->getKey(), 'conversation_id' => $conversation->getKey(), 'provider_message_id' => 'wamid.already-delivered',
        'direction' => 'outbound', 'type' => 'template', 'status' => 'delivered', 'delivered_at' => now()->subMinutes(3),
    ]);
    WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(), 'phone_number_id' => 'phone-number-1', 'access_token' => 'secret-token', 'status' => 'connected',
    ]);

    $failure = WebhookEvent::query()->create([
        'business_id' => $business->getKey(), 'provider' => 'whatsapp', 'event_id' => 'status:failed-after-sent',
        'event_type' => 'status', 'payload' => [
            'kind' => 'status', 'phone_number_id' => 'phone-number-1', 'provider_message_id' => 'wamid.failed-after-sent',
            'status' => 'failed', 'timestamp' => now()->timestamp, 'error_code' => '131026',
        ], 'status' => 'received',
    ]);
    $staleSent = WebhookEvent::query()->create([
        'business_id' => $business->getKey(), 'provider' => 'whatsapp', 'event_id' => 'status:stale-sent-after-failure',
        'event_type' => 'status', 'payload' => [
            'kind' => 'status', 'phone_number_id' => 'phone-number-1', 'provider_message_id' => 'wamid.failed-after-sent',
            'status' => 'sent', 'timestamp' => now()->subMinutes(8)->timestamp, 'error_code' => null,
        ], 'status' => 'received',
    ]);
    $staleDelivered = WebhookEvent::query()->create([
        'business_id' => $business->getKey(), 'provider' => 'whatsapp', 'event_id' => 'status:stale-delivered-after-failure',
        'event_type' => 'status', 'payload' => [
            'kind' => 'status', 'phone_number_id' => 'phone-number-1', 'provider_message_id' => 'wamid.failed-after-sent',
            'status' => 'delivered', 'timestamp' => now()->subMinutes(9)->timestamp, 'error_code' => null,
        ], 'status' => 'received',
    ]);
    $staleFailure = WebhookEvent::query()->create([
        'business_id' => $business->getKey(), 'provider' => 'whatsapp', 'event_id' => 'status:stale-failure-after-delivery',
        'event_type' => 'status', 'payload' => [
            'kind' => 'status', 'phone_number_id' => 'phone-number-1', 'provider_message_id' => 'wamid.already-delivered',
            'status' => 'failed', 'timestamp' => now()->subMinutes(4)->timestamp, 'error_code' => '131026',
        ], 'status' => 'received',
    ]);

    foreach ([$failure, $staleSent, $staleDelivered, $staleFailure] as $event) {
        (new ProcessWhatsAppWebhookJob((int) $event->getKey()))->handle(
            app(PhoneNumberNormalizer::class),
            app(RecordConsentCommand::class),
            app(CancelCustomerFollowUpsCommand::class),
            app(GetWhatsAppMessagingSettingsQuery::class),
        );
    }

    expect($message->fresh()->status)->toBe('failed')
        ->and($message->fresh()->failed_at)->not->toBeNull()
        ->and($message->fresh()->provider_error_code)->toBe('131026')
        ->and($deliveredMessage->fresh()->status)->toBe('delivered');
});

it('holds early delivery callbacks until the accepted outbound message record arrives', function (): void {
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $conversation = Conversation::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp', 'status' => 'open',
    ]);
    WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(), 'phone_number_id' => 'phone-number-1', 'access_token' => 'secret-token', 'status' => 'connected',
    ]);
    $event = WebhookEvent::query()->create([
        'business_id' => $business->getKey(), 'provider' => 'whatsapp', 'event_id' => 'status:early-delivery-event',
        'event_type' => 'status', 'payload' => [
            'kind' => 'status', 'phone_number_id' => 'phone-number-1', 'provider_message_id' => 'wamid.outbound-late',
            'status' => 'delivered', 'timestamp' => now()->timestamp, 'error_code' => null,
        ], 'status' => 'received',
    ]);
    $job = new ProcessWhatsAppWebhookJob((int) $event->getKey());
    $job->handle(app(PhoneNumberNormalizer::class), app(RecordConsentCommand::class), app(CancelCustomerFollowUpsCommand::class), app(GetWhatsAppMessagingSettingsQuery::class));

    expect($event->fresh()->status)->toBe('awaiting_message');
    $message = Message::query()->create([
        'business_id' => $business->getKey(), 'conversation_id' => $conversation->getKey(),
        'provider_message_id' => 'wamid.outbound-late', 'direction' => 'outbound', 'type' => 'text',
        'status' => 'sent', 'body' => 'Thanks', 'sent_at' => now()->subMinute(),
    ]);
    (new ProcessWhatsAppWebhookJob((int) $event->getKey()))->handle(app(PhoneNumberNormalizer::class), app(RecordConsentCommand::class), app(CancelCustomerFollowUpsCommand::class), app(GetWhatsAppMessagingSettingsQuery::class));

    expect($message->fresh()->status)->toBe('delivered')
        ->and($event->fresh()->status)->toBe('processed');
});

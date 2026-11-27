<?php

use App\Models\AutomationRule;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Role;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\Automations\Commands\RecoverStalledMessagesCommand;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('marks a manual template dispatch as uncertain when its request process stalled', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $inactive = User::factory()->create();
    $messagesOnly = User::factory()->create();
    Role::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Message history auditor',
        'slug' => 'message_history_auditor',
        'permissions' => ['messages.view'],
    ]);
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($viewer, ['role' => 'manager', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($inactive, ['role' => 'owner', 'status' => 'suspended', 'joined_at' => now()]);
    $business->members()->attach($messagesOnly, ['role' => 'message_history_auditor', 'status' => 'active', 'joined_at' => now()]);
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $conversation = Conversation::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'status' => 'open',
    ]);
    $message = Message::query()->create([
        'business_id' => $business->getKey(),
        'conversation_id' => $conversation->getKey(),
        'idempotency_key' => 'e8169a62-44ab-4edb-8a46-82ddb2fdd89f',
        'direction' => 'outbound',
        'type' => 'template',
        'status' => 'pending',
    ]);
    $message->forceFill(['created_at' => now()->subMinutes(20)])->save();

    $result = app(RecoverStalledMessagesCommand::class)->execute();

    expect($result['manual_delivery_uncertain'])->toBe(1)
        ->and($message->fresh()->status)->toBe('delivery_uncertain');
    $this->assertDatabaseHas('message_events', [
        'message_id' => $message->getKey(),
        'event_type' => 'message.send_uncertain',
        'status' => 'delivery_uncertain',
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'business_id' => $business->getKey(),
        'auditable_id' => $message->getKey(),
        'action' => 'message.manual_template_reconciliation_required',
    ]);
    expect($owner->notifications()->count())->toBe(1)
        ->and($viewer->notifications()->count())->toBe(1)
        ->and($inactive->notifications()->count())->toBe(0)
        ->and($messagesOnly->notifications()->count())->toBe(0)
        ->and($owner->notifications()->firstOrFail()->data)->toBe([
            'title' => 'WhatsApp delivery needs review',
            'message' => 'A WhatsApp message has an uncertain delivery outcome. Check its history before retrying.',
            'url' => '/inbox/'.$conversation->getKey(),
        ]);
    expect(app(RecoverStalledMessagesCommand::class)->execute()['manual_delivery_uncertain'])->toBe(0)
        ->and($owner->notifications()->count())->toBe(1);
});

it('marks a crashed free-form reply uncertain and notifies staff with the inbox link', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $conversation = Conversation::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'status' => 'open',
    ]);
    $message = Message::query()->create([
        'business_id' => $business->getKey(),
        'conversation_id' => $conversation->getKey(),
        'direction' => 'outbound',
        'type' => 'text',
        'status' => 'pending',
        'body' => 'Reply whose provider result was lost after the process crashed.',
    ]);
    $message->forceFill(['created_at' => now()->subMinutes(20)])->save();

    expect(app(RecoverStalledMessagesCommand::class)->execute()['manual_delivery_uncertain'])->toBe(1)
        ->and($message->fresh()->status)->toBe('delivery_uncertain');
    $this->assertDatabaseHas('audit_logs', [
        'business_id' => $business->getKey(),
        'auditable_id' => $message->getKey(),
        'action' => 'message.manual_text_reconciliation_required',
    ]);
    expect($owner->notifications()->firstOrFail()->data['url'])->toBe('/inbox/'.$conversation->getKey())
        ->and(app(RecoverStalledMessagesCommand::class)->execute()['manual_delivery_uncertain'])->toBe(0)
        ->and($owner->notifications()->count())->toBe(1);
});

it('notifies authorized staff when a scheduled provider send becomes uncertain', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($viewer, ['role' => 'viewer', 'status' => 'active', 'joined_at' => now()]);
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'follow_up',
        'language' => 'en',
        'purpose' => 'thank_you',
    ]);
    $rule = AutomationRule::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Follow up',
        'trigger' => 'visit_completed',
        'action' => 'send_template',
        'delay_minutes' => 0,
        'is_enabled' => true,
        'settings' => ['template_id' => $template->getKey()],
    ]);
    CustomerConsent::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'granted' => true,
        'source' => 'test',
        'recorded_at' => now(),
    ]);
    $message = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'automation_rule_id' => $rule->getKey(),
        'channel' => 'whatsapp',
        'idempotency_key' => 'uncertain-scheduled-send',
        'payload' => ['template_id' => $template->getKey()],
        'status' => 'sending',
        'scheduled_at' => now()->subHour(),
        'claimed_at' => now()->subMinutes(20),
    ]);

    expect(app(RecoverStalledMessagesCommand::class)->execute()['reconciliation_required'])->toBe(1)
        ->and($message->fresh()->status)->toBe('reconciliation_required')
        ->and($owner->notifications()->count())->toBe(1)
        ->and($viewer->notifications()->count())->toBe(0);
});

it('leaves recent manual messages unchanged during recovery', function (): void {
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $conversation = Conversation::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'status' => 'open',
    ]);
    $recent = Message::query()->create([
        'business_id' => $business->getKey(),
        'conversation_id' => $conversation->getKey(),
        'idempotency_key' => 'b27294e3-a217-4bbd-90aa-4349a33f8d67',
        'direction' => 'outbound',
        'type' => 'template',
        'status' => 'pending',
    ]);
    $textMessage = Message::query()->create([
        'business_id' => $business->getKey(),
        'conversation_id' => $conversation->getKey(),
        'direction' => 'outbound',
        'type' => 'text',
        'status' => 'pending',
    ]);
    $result = app(RecoverStalledMessagesCommand::class)->execute();

    expect($result['manual_delivery_uncertain'])->toBe(0)
        ->and($recent->fresh()->status)->toBe('pending')
        ->and($textMessage->fresh()->status)->toBe('pending');
});

<?php

use App\Models\AutomationRule;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\ScheduledMessage;
use App\Models\Visit;
use App\Services\Automations\Commands\RecoverSentMessageFollowUpsCommand;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('recovers the next follow-up for a sent message after an interruption and remains idempotent', function (): void {
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $visit = Visit::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'visited_at' => now()->subDays(2),
    ]);
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(),
        'provider_template_id' => 'provider-follow-up',
        'name' => 'visit_follow_up',
        'language' => 'en',
        'category' => 'utility',
        'purpose' => 'thank_you',
        'status' => 'synchronized',
        'provider_status' => 'APPROVED',
        'is_enabled' => true,
        'components' => [['type' => 'BODY', 'text' => 'Thank you']],
        'variable_map' => [],
    ]);
    $rule = AutomationRule::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Visit follow-up',
        'trigger' => 'visit_completed',
        'action' => 'send_template',
        'delay_minutes' => 1,
        'is_enabled' => true,
        'settings' => ['template_id' => $template->getKey(), 'max_follow_ups' => 3, 'repeat_interval_minutes' => 10080],
    ]);
    CustomerConsent::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'granted' => true,
        'source' => 'test',
        'recorded_at' => now(),
    ]);
    $conversation = Conversation::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'status' => 'open',
    ]);
    $sentMessage = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'automation_rule_id' => $rule->getKey(),
        'conversation_id' => $conversation->getKey(),
        'channel' => 'whatsapp',
        'idempotency_key' => 'visit:'.$visit->getKey().':rule:'.$rule->getKey().':step:1',
        'payload' => ['follow_up_number' => 1, 'visit_id' => $visit->getKey(), 'trigger' => 'visit_completed'],
        'status' => 'sent',
        'scheduled_at' => now()->subDay(),
        'sent_at' => now()->subHour(),
    ]);

    $command = app(RecoverSentMessageFollowUpsCommand::class);

    expect($command->execute())->toBe(1)
        ->and($sentMessage->fresh()->follow_up_reconciled_at)->not->toBeNull()
        ->and(ScheduledMessage::query()->where('idempotency_key', 'visit:'.$visit->getKey().':rule:'.$rule->getKey().':step:2')->count())->toBe(1);

    expect($command->execute())->toBe(0)
        ->and(ScheduledMessage::query()->where('business_id', $business->getKey())->count())->toBe(2);
});

it('marks sent workflows reconciled without scheduling after a customer response', function (): void {
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $visit = Visit::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'visited_at' => now()->subDays(2),
    ]);
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(),
        'provider_template_id' => 'provider-follow-up-response',
        'name' => 'visit_follow_up',
        'language' => 'en',
        'category' => 'utility',
        'purpose' => 'thank_you',
        'status' => 'synchronized',
        'provider_status' => 'APPROVED',
        'is_enabled' => true,
        'components' => [['type' => 'BODY', 'text' => 'Thank you']],
        'variable_map' => [],
    ]);
    $rule = AutomationRule::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Visit follow-up',
        'trigger' => 'visit_completed',
        'action' => 'send_template',
        'delay_minutes' => 1,
        'is_enabled' => true,
        'settings' => ['template_id' => $template->getKey(), 'max_follow_ups' => 3, 'repeat_interval_minutes' => 10080],
    ]);
    CustomerConsent::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'granted' => true,
        'source' => 'test',
        'recorded_at' => now(),
    ]);
    $conversation = Conversation::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'status' => 'open',
    ]);
    $sentMessage = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'automation_rule_id' => $rule->getKey(),
        'conversation_id' => $conversation->getKey(),
        'channel' => 'whatsapp',
        'idempotency_key' => 'visit:'.$visit->getKey().':rule:'.$rule->getKey().':step:1',
        'payload' => ['follow_up_number' => 1, 'visit_id' => $visit->getKey(), 'trigger' => 'visit_completed'],
        'status' => 'sent',
        'scheduled_at' => now()->subDay(),
        'sent_at' => now()->subHour(),
    ]);
    Message::query()->create([
        'business_id' => $business->getKey(),
        'conversation_id' => $conversation->getKey(),
        'provider_message_id' => 'wamid.customer-reply-before-recovery',
        'direction' => 'inbound',
        'type' => 'text',
        'status' => 'received',
        'body' => 'Thanks, I will be back.',
        'sent_at' => now(),
    ]);
    $conversation->forceFill(['last_customer_message_at' => now()])->save();

    expect(app(RecoverSentMessageFollowUpsCommand::class)->execute())->toBe(1)
        ->and($sentMessage->fresh()->follow_up_reconciled_at)->not->toBeNull()
        ->and(ScheduledMessage::query()->where('business_id', $business->getKey())->count())->toBe(1);
});

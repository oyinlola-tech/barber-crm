<?php

use App\Interfaces\Apis\MessagingProviderInterface;
use App\Models\AutomationRule;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\ScheduledMessage;
use App\Models\Visit;
use App\Models\WhatsAppConnection;
use App\Services\Automations\Commands\ProcessScheduledMessageCommand;
use App\Services\Automations\Commands\ScheduleFollowUpCommand;
use App\Services\Consent\Queries\CustomerCanReceiveMessageQuery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('sends a claimed message once even when its job is delivered twice', function (): void {
    $this->travelTo(now('Africa/Lagos')->setTime(10, 0)->utc());
    config(['whatsapp.api_version' => 'v99.0']);
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey(), 'phone' => '+2348012345678']);
    $template = MessageTemplate::query()->create(['business_id' => $business->getKey(), 'provider_template_id' => 'template-123', 'name' => 'visit_follow_up', 'language' => 'en', 'category' => 'utility', 'purpose' => 'thank_you', 'status' => 'synchronized', 'provider_status' => 'APPROVED', 'is_enabled' => true, 'components' => [['type' => 'BODY', 'text' => 'Thank you']], 'variable_map' => []]);
    $rule = AutomationRule::query()->create(['business_id' => $business->getKey(), 'name' => 'Follow up', 'trigger' => 'visit_completed', 'action' => 'send_template', 'delay_minutes' => 10, 'is_enabled' => true, 'settings' => ['template_id' => $template->getKey()]]);
    WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(),
        'phone_number_id' => 'phone-number-1',
        'business_account_id' => 'business-account-1',
        'access_token' => 'encrypted-token',
        'status' => 'connected',
    ]);
    CustomerConsent::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'granted' => true,
        'source' => 'test',
        'recorded_at' => now(),
    ]);
    $scheduledMessage = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'automation_rule_id' => $rule->getKey(),
        'idempotency_key' => 'processing:once',
        'payload' => ['template_id' => $template->getKey(), 'template_name' => 'visit_follow_up', 'language' => 'en', 'variables' => [], 'max_attempts' => 3],
        'status' => 'queued',
        'scheduled_at' => now(),
    ]);
    $messaging = Mockery::mock(MessagingProviderInterface::class);
    $messaging->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(fn ($connection, string $recipient, string $templateName, string $language, array $options): bool => $connection->business_id === $business->getKey()
            && $recipient === '+2348012345678'
            && $templateName === 'visit_follow_up'
            && $language === 'en'
            && $options === ['components' => []]
        )
        ->andReturn(['messages' => [['id' => 'wamid.123']]]);
    app()->instance(MessagingProviderInterface::class, $messaging);
    $command = app(ProcessScheduledMessageCommand::class);

    $command->execute($scheduledMessage->getKey());
    $command->execute($scheduledMessage->getKey());

    expect($scheduledMessage->fresh()->status)->toBe('sent');
    expect($scheduledMessage->fresh()->follow_up_reconciled_at)->not->toBeNull();
    $this->assertDatabaseCount('messages', 1);
});

it('cancels a claimed follow-up when the customer replies before provider dispatch', function (): void {
    $this->travelTo(now('Africa/Lagos')->setTime(10, 0)->utc());
    config(['whatsapp.api_version' => 'v99.0']);
    $business = Business::factory()->create(['timezone' => 'Africa/Lagos']);
    $customer = Customer::factory()->create(['business_id' => $business->getKey(), 'phone' => '+2348012345678']);
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(), 'provider_template_id' => 'provider-reply-race', 'name' => 'visit_follow_up',
        'language' => 'en', 'category' => 'utility', 'purpose' => 'thank_you', 'status' => 'synchronized',
        'provider_status' => 'APPROVED', 'is_enabled' => true, 'components' => [['type' => 'BODY', 'text' => 'Thank you']], 'variable_map' => [],
    ]);
    $rule = AutomationRule::query()->create([
        'business_id' => $business->getKey(), 'name' => 'Follow up', 'trigger' => 'visit_completed',
        'action' => 'send_template', 'delay_minutes' => 10, 'is_enabled' => true,
        'settings' => ['template_id' => $template->getKey(), 'max_follow_ups' => 1],
    ]);
    CustomerConsent::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp',
        'granted' => true, 'source' => 'test', 'recorded_at' => now()->subDay(),
    ]);
    WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(), 'phone_number_id' => 'phone-number-1',
        'business_account_id' => 'business-account-1', 'access_token' => 'encrypted-token', 'status' => 'connected',
    ]);
    $conversation = Conversation::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp', 'status' => 'open',
    ]);
    $scheduled = app(ScheduleFollowUpCommand::class)->execute(
        (int) $business->getKey(),
        (int) $customer->getKey(),
        (int) $rule->getKey(),
        now()->utc(),
        ['follow_up_number' => 1, 'visit_id' => 123],
        'visit:123:rule:'.$rule->getKey().':step:1',
    );
    $scheduled->update(['status' => 'queued']);

    $consent = Mockery::mock(CustomerCanReceiveMessageQuery::class);
    $consentCalls = 0;
    $consent->shouldReceive('execute')->twice()->andReturnUsing(function () use (&$consentCalls, $business, $conversation): bool {
        $consentCalls++;
        if ($consentCalls === 2) {
            Message::query()->create([
                'business_id' => $business->getKey(), 'conversation_id' => $conversation->getKey(),
                'provider_message_id' => 'wamid.customer-replied-during-claim', 'direction' => 'inbound',
                'type' => 'text', 'status' => 'received', 'body' => 'Thanks, I will be back.', 'sent_at' => now(),
            ]);
            $conversation->forceFill(['last_customer_message_at' => now()])->save();
        }

        return true;
    });
    app()->instance(CustomerCanReceiveMessageQuery::class, $consent);
    $messaging = Mockery::mock(MessagingProviderInterface::class);
    $messaging->shouldNotReceive('sendTemplate');
    app()->instance(MessagingProviderInterface::class, $messaging);

    app(ProcessScheduledMessageCommand::class)->execute((int) $scheduled->getKey());

    expect($scheduled->fresh()->status)->toBe('cancelled')
        ->and($scheduled->fresh()->last_error)->toBe('Customer eligibility changed before provider dispatch.')
        ->and(Message::query()->where('direction', 'outbound')->count())->toBe(0);
});

it('cancels a visit follow-up when the customer is archived during the claim window', function (): void {
    $this->travelTo(now('Africa/Lagos')->setTime(10, 0)->utc());
    config(['whatsapp.api_version' => 'v99.0']);
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey(), 'phone' => '+2348012345678']);
    $visit = Visit::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'visited_at' => now()->subDay(),
    ]);
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(), 'provider_template_id' => 'archive-race-template', 'name' => 'visit_follow_up',
        'language' => 'en', 'category' => 'utility', 'purpose' => 'thank_you', 'status' => 'synchronized',
        'provider_status' => 'APPROVED', 'is_enabled' => true, 'components' => [['type' => 'BODY', 'text' => 'Thank you']], 'variable_map' => [],
    ]);
    $rule = AutomationRule::query()->create([
        'business_id' => $business->getKey(), 'name' => 'Visit follow up', 'trigger' => 'visit_completed',
        'action' => 'send_template', 'delay_minutes' => 10, 'is_enabled' => true,
        'settings' => ['template_id' => $template->getKey(), 'max_follow_ups' => 1],
    ]);
    CustomerConsent::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp',
        'granted' => true, 'source' => 'test', 'recorded_at' => now(),
    ]);
    WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(), 'phone_number_id' => 'phone-number-1',
        'business_account_id' => 'business-account-1', 'access_token' => 'encrypted-token', 'status' => 'connected',
    ]);
    $scheduled = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'automation_rule_id' => $rule->getKey(),
        'channel' => 'whatsapp',
        'idempotency_key' => 'visit:'.$visit->getKey().':rule:'.$rule->getKey().':step:1',
        'payload' => ['template_id' => $template->getKey(), 'template_name' => $template->name, 'language' => 'en', 'trigger' => 'visit_completed', 'visit_id' => $visit->getKey()],
        'status' => 'queued',
        'scheduled_at' => now(),
    ]);

    $consent = Mockery::mock(CustomerCanReceiveMessageQuery::class);
    $consentCalls = 0;
    $consent->shouldReceive('execute')->twice()->andReturnUsing(function () use (&$consentCalls, $customer): bool {
        $consentCalls++;
        if ($consentCalls === 2) {
            $customer->update(['status' => 'archived', 'archived_at' => now()]);
        }

        return true;
    });
    app()->instance(CustomerCanReceiveMessageQuery::class, $consent);
    $messaging = Mockery::mock(MessagingProviderInterface::class);
    $messaging->shouldNotReceive('sendTemplate');
    app()->instance(MessagingProviderInterface::class, $messaging);

    app(ProcessScheduledMessageCommand::class)->execute((int) $scheduled->getKey());

    expect($scheduled->fresh()->status)->toBe('cancelled')
        ->and(Message::query()->where('direction', 'outbound')->count())->toBe(0);
});

it('cancels inactive-customer sends when the customer states a return date during the claim window', function (): void {
    $this->travelTo(now('Africa/Lagos')->setTime(10, 0)->utc());
    config(['whatsapp.api_version' => 'v99.0']);
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey(), 'phone' => '+2348012345678']);
    $visit = Visit::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'visited_at' => now('UTC')->subDays(45),
    ]);
    $template = MessageTemplate::query()->create(['business_id' => $business->getKey(), 'provider_template_id' => 'template-123', 'name' => 'return_reminder', 'language' => 'en', 'category' => 'marketing', 'purpose' => 'reengagement', 'status' => 'synchronized', 'provider_status' => 'APPROVED', 'is_enabled' => true, 'components' => [['type' => 'BODY', 'text' => 'Come back soon']], 'variable_map' => []]);
    $rule = AutomationRule::query()->create(['business_id' => $business->getKey(), 'name' => 'Re-engagement', 'trigger' => 'customer_inactive', 'action' => 'send_template', 'delay_minutes' => 0, 'is_enabled' => true, 'settings' => ['template_id' => $template->getKey(), 'inactive_after_days' => 30, 'max_follow_ups' => 2, 'repeat_interval_minutes' => 10080]]);
    WhatsAppConnection::query()->create(['business_id' => $business->getKey(), 'phone_number_id' => 'phone-number-1', 'business_account_id' => 'business-account-1', 'access_token' => 'encrypted-token', 'status' => 'connected']);
    CustomerConsent::query()->create(['business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp', 'granted' => true, 'source' => 'test', 'recorded_at' => now()]);
    $scheduled = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'automation_rule_id' => $rule->getKey(),
        'channel' => 'whatsapp',
        'idempotency_key' => 'inactive:customer:'.$customer->getKey().':rule:'.$rule->getKey().':visit:'.now('UTC')->subDays(45)->format('YmdHis').':cycle:0:step:1',
        'payload' => ['template_id' => $template->getKey(), 'template_name' => 'return_reminder', 'language' => 'en', 'trigger' => 'customer_inactive', 'inactivity_anchor' => $visit->visited_at->toIso8601String()],
        'status' => 'queued',
        'scheduled_at' => now(),
    ]);
    $consent = Mockery::mock(CustomerCanReceiveMessageQuery::class);
    $consentCalls = 0;
    $consent->shouldReceive('execute')->twice()->andReturnUsing(function () use (&$consentCalls, $customer): bool {
        $consentCalls++;
        if ($consentCalls === 2) {
            $customer->update(['intended_return_date' => now('Africa/Lagos')->addDays(5)->toDateString()]);
        }

        return true;
    });
    app()->instance(CustomerCanReceiveMessageQuery::class, $consent);
    $messaging = Mockery::mock(MessagingProviderInterface::class);
    $messaging->shouldNotReceive('sendTemplate');
    app()->instance(MessagingProviderInterface::class, $messaging);

    app(ProcessScheduledMessageCommand::class)->execute($scheduled->getKey());

    expect($scheduled->fresh()->status)->toBe('cancelled');
});

it('suppresses visit-triggered return reminders when a future return date is recorded', function (): void {
    $this->travelTo(now('Africa/Lagos')->setTime(10, 0)->utc());
    config(['whatsapp.api_version' => 'v99.0']);
    $business = Business::factory()->create(['timezone' => 'Africa/Lagos']);
    $customer = Customer::factory()->create([
        'business_id' => $business->getKey(),
        'phone' => '+2348012345678',
        'intended_return_date' => now('Africa/Lagos')->addDays(5)->toDateString(),
    ]);
    $visit = Visit::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'visited_at' => now('UTC')->subDays(3),
    ]);
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(), 'provider_template_id' => 'template-return-intention',
        'name' => 'return_intention', 'language' => 'en', 'category' => 'marketing',
        'purpose' => 'return_intention', 'status' => 'synchronized', 'provider_status' => 'APPROVED',
        'is_enabled' => true, 'components' => [['type' => 'BODY', 'text' => 'When will you return?']], 'variable_map' => [],
    ]);
    $rule = AutomationRule::query()->create([
        'business_id' => $business->getKey(), 'name' => 'Ask return intention', 'trigger' => 'visit_completed',
        'action' => 'send_template', 'delay_minutes' => 0, 'is_enabled' => true,
        'settings' => ['template_id' => $template->getKey(), 'max_follow_ups' => 1],
    ]);
    WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(), 'phone_number_id' => 'phone-number-1',
        'business_account_id' => 'business-account-1', 'access_token' => 'encrypted-token', 'status' => 'connected',
    ]);
    CustomerConsent::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp',
        'granted' => true, 'source' => 'test', 'recorded_at' => now(),
    ]);
    $scheduled = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(),
        'automation_rule_id' => $rule->getKey(), 'channel' => 'whatsapp', 'idempotency_key' => 'visit-return-intention',
        'payload' => [
            'template_id' => $template->getKey(), 'template_name' => $template->name,
            'language' => $template->language, 'trigger' => 'visit_completed', 'visit_id' => $visit->getKey(),
        ],
        'status' => 'queued', 'scheduled_at' => now(),
    ]);
    $messaging = Mockery::mock(MessagingProviderInterface::class);
    $messaging->shouldNotReceive('sendTemplate');
    app()->instance(MessagingProviderInterface::class, $messaging);

    app(ProcessScheduledMessageCommand::class)->execute((int) $scheduled->getKey());

    expect($scheduled->fresh()->status)->toBe('cancelled')
        ->and(Message::query()->where('direction', 'outbound')->count())->toBe(0);
});

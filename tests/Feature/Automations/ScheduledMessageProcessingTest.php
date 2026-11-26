<?php

use App\Interfaces\Apis\MessagingProviderInterface;
use App\Models\AutomationRule;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Models\MessageTemplate;
use App\Models\ScheduledMessage;
use App\Models\Visit;
use App\Models\WhatsAppConnection;
use App\Services\Automations\Commands\ProcessScheduledMessageCommand;
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
    $this->assertDatabaseCount('messages', 1);
});

it('cancels inactive-customer sends when the customer states a return date after scheduling', function (): void {
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
    $messaging = Mockery::mock(MessagingProviderInterface::class);
    $messaging->shouldNotReceive('sendTemplate');
    app()->instance(MessagingProviderInterface::class, $messaging);
    $customer->update(['intended_return_date' => now('Africa/Lagos')->addDays(5)->toDateString()]);

    app(ProcessScheduledMessageCommand::class)->execute($scheduled->getKey());

    expect($scheduled->fresh()->status)->toBe('cancelled');
});

<?php

use App\Models\AutomationRule;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Models\MessageTemplate;
use App\Services\Automations\Commands\ScheduleFollowUpCommand;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('returns the existing scheduled message for a repeated idempotency key', function (): void {
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    CustomerConsent::query()->create(['business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp', 'granted' => true, 'source' => 'test', 'recorded_at' => now()]);
    $template = MessageTemplate::query()->create(['business_id' => $business->getKey(), 'provider_template_id' => 'provider-1', 'name' => 'reminder', 'language' => 'en', 'category' => 'utility', 'purpose' => 'thank_you', 'status' => 'synchronized', 'provider_status' => 'APPROVED', 'is_enabled' => true, 'components' => [['type' => 'BODY', 'text' => 'Hello']], 'variable_map' => []]);
    $rule = AutomationRule::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Visit follow up',
        'trigger' => 'visit_completed',
        'action' => 'send_template',
        'delay_minutes' => 0,
        'is_enabled' => true,
        'settings' => ['template_id' => $template->getKey()],
    ]);
    $command = app(ScheduleFollowUpCommand::class);
    $scheduledAt = now()->addDay();

    $first = $command->execute($business->getKey(), $customer->getKey(), $rule->getKey(), $scheduledAt, ['visit_id' => 42], 'visit:42:reminder');
    $second = $command->execute($business->getKey(), $customer->getKey(), $rule->getKey(), $scheduledAt, ['visit_id' => 42], 'visit:42:reminder');

    expect($second->getKey())->toBe($first->getKey())
        ->and($first->fresh()->business_id)->toBe($business->getKey());
    $this->assertDatabaseCount('scheduled_messages', 1);
});

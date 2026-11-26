<?php

use App\Models\AutomationRule;
use App\Models\Business;
use App\Models\Customer;
use App\Models\ScheduledMessage;
use App\Services\Automations\Commands\RecordScheduledMessageJobFailureCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns a queue-failed scheduled message to pending with a safe fallback delay', function (): void {
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $rule = AutomationRule::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Visit follow-up',
        'trigger' => 'visit_completed',
        'action' => 'send_template',
        'delay_minutes' => 0,
        'is_enabled' => true,
        'settings' => [],
    ]);
    $message = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'automation_rule_id' => $rule->getKey(),
        'channel' => 'whatsapp',
        'idempotency_key' => 'queue-failure:fallback',
        'payload' => ['queue_attempts' => 0, 'max_attempts' => 3],
        'status' => 'queued',
        'scheduled_at' => now()->subMinute(),
        'claimed_at' => now()->subMinute(),
        'failed_at' => now()->subMinutes(2),
    ]);
    config(['automation.retry_backoff_seconds' => []]);
    $before = now();

    app(RecordScheduledMessageJobFailureCommand::class)->execute((int) $message->getKey());

    $message->refresh();
    expect($message->status)->toBe('pending')
        ->and($message->claimed_at)->toBeNull()
        ->and($message->failed_at)->toBeNull()
        ->and($message->scheduled_at->greaterThanOrEqualTo($before->copy()->addSeconds(59)))->toBeTrue()
        ->and($message->payload['queue_attempts'])->toBe(1);
});

<?php

use App\Models\Business;
use App\Models\Customer;
use App\Models\ScheduledMessage;
use App\Services\Consent\Commands\RecordConsentCommand;
use App\Services\Consent\Queries\CustomerCanReceiveMessageQuery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('revokes active consent and cancels pending messages after opt-out', function (): void {
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $command = app(RecordConsentCommand::class);
    $command->execute($business->getKey(), $customer->getKey(), 'whatsapp', true, 'in_person');
    $scheduledMessage = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'idempotency_key' => 'customer:'.$customer->getKey().':test',
        'status' => 'pending',
        'scheduled_at' => now()->addHour(),
    ]);
    $queuedMessage = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'idempotency_key' => 'customer:'.$customer->getKey().':queued-test',
        'status' => 'queued',
        'scheduled_at' => now(),
    ]);
    $sendingMessage = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'idempotency_key' => 'customer:'.$customer->getKey().':sending-test',
        'status' => 'sending',
        'scheduled_at' => now(),
    ]);
    $uncertainMessage = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'idempotency_key' => 'customer:'.$customer->getKey().':uncertain-test',
        'status' => 'reconciliation_required',
        'scheduled_at' => now(),
    ]);

    $command->execute($business->getKey(), $customer->getKey(), 'whatsapp', false, 'customer_request');

    expect(app(CustomerCanReceiveMessageQuery::class)->execute($business->getKey(), $customer->getKey(), 'whatsapp'))->toBeFalse();
    expect($scheduledMessage->fresh()->status)->toBe('cancelled')
        ->and($queuedMessage->fresh()->status)->toBe('cancelled')
        ->and($sendingMessage->fresh()->status)->toBe('sending')
        ->and($uncertainMessage->fresh()->status)->toBe('reconciliation_required');
});

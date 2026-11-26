<?php

use App\Models\Business;
use App\Models\Customer;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\Consent\Commands\RecordConsentCommand;
use App\Services\Consent\Queries\CustomerCanReceiveMessageQuery;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(LazilyRefreshDatabase::class);

it('enforces consent permissions inside the CQRS command when invoked directly', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $viewer = User::factory()->create();
    $business->members()->attach($viewer, [
        'role' => 'viewer',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);

    expect(fn () => app(RecordConsentCommand::class)->execute(
        (int) $business->getKey(),
        (int) $customer->getKey(),
        'whatsapp',
        true,
        'in_person',
        (int) $viewer->getKey(),
    ))->toThrow(HttpException::class);

    expect($customer->consents()->count())->toBe(0);
});

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

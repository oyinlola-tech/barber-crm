<?php

use App\Models\Business;
use App\Models\Customer;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\Consent\Commands\RecordConsentCommand;
use App\Services\Consent\Queries\CustomerCanReceiveMessageQuery;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
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
        'customer_follow_up',
    ))->toThrow(HttpException::class);

    expect($customer->consents()->count())->toBe(0);
});

it('requires an explicit purpose before granting messaging consent', function (): void {
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    $business->members()->attach($owner, [
        'role' => 'owner',
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
        (int) $owner->getKey(),
    ))->toThrow(ValidationException::class);

    expect($customer->consents()->count())->toBe(0);
});

it('rejects actorless consent grants while allowing actorless customer opt-outs', function (): void {
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $command = app(RecordConsentCommand::class);

    expect(fn () => $command->execute(
        (int) $business->getKey(),
        (int) $customer->getKey(),
        'whatsapp',
        true,
        'in_person',
        null,
        'customer_follow_up',
    ))->toThrow(AuthorizationException::class);

    expect($customer->consents()->count())->toBe(0);

    $command->execute(
        (int) $business->getKey(),
        (int) $customer->getKey(),
        'whatsapp',
        false,
        'whatsapp_keyword',
    );

    expect($customer->consents()->where('granted', false)->count())->toBe(1);
});

it('revokes active consent and cancels pending messages after opt-out', function (): void {
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $owner = User::factory()->create();
    $business->members()->attach($owner, [
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    $command = app(RecordConsentCommand::class);
    $command->execute($business->getKey(), $customer->getKey(), 'whatsapp', true, 'in_person', (int) $owner->getKey(), 'customer_follow_up', 'consent-register-17');
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

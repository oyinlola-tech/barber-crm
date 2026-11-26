<?php

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Services\Automations\Commands\RecoverStalledMessagesCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('marks a manual template dispatch as uncertain when its request process stalled', function (): void {
    $business = Business::factory()->create();
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
});

it('leaves recent or non-template manual messages unchanged during recovery', function (): void {
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
    $textMessage->forceFill(['created_at' => now()->subMinutes(20)])->save();

    $result = app(RecoverStalledMessagesCommand::class)->execute();

    expect($result['manual_delivery_uncertain'])->toBe(0)
        ->and($recent->fresh()->status)->toBe('pending')
        ->and($textMessage->fresh()->status)->toBe('pending');
});

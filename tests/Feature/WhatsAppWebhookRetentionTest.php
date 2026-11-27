<?php

use App\Configs\WhatsAppConfig;
use App\Jobs\WhatsApp\ProcessWhatsAppWebhookJob;
use App\Models\Business;
use App\Models\MessageEvent;
use App\Models\WebhookEvent;
use App\Services\Webhooks\Commands\RedactExpiredWebhookPayloadsCommand;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

uses(LazilyRefreshDatabase::class);

it('redacts normalized customer content immediately after terminal webhook failure', function (): void {
    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'event_id' => 'message:wamid.failed',
        'event_type' => 'message',
        'payload' => ['kind' => 'message', 'body' => 'Private customer message'],
        'status' => 'received',
    ]);

    (new ProcessWhatsAppWebhookJob((int) $event->getKey()))->failed(new RuntimeException('Provider data must not be persisted in failure details.'));

    $event->refresh();
    expect($event->status)->toBe('failed')
        ->and($event->payload)->toBe(['redacted' => true])
        ->and($event->processed_at)->not->toBeNull();
    $this->assertDatabaseHas('webhook_events', ['id' => $event->getKey(), 'event_id' => 'message:wamid.failed']);
});

it('redacts only expired terminal WhatsApp webhook payloads and retains deduplication tombstones', function (): void {
    Carbon::setTestNow('2026-11-26 12:00:00');
    config(['whatsapp.webhook_payload_retention_days' => 30]);

    $expired = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'event_id' => 'message:wamid.expired',
        'event_type' => 'message',
        'payload' => ['kind' => 'message', 'body' => 'Old customer text'],
        'status' => 'processed',
    ]);
    $expired->forceFill(['updated_at' => now()->subDays(31)])->saveQuietly();
    $recent = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'event_id' => 'message:wamid.recent',
        'event_type' => 'message',
        'payload' => ['kind' => 'message', 'body' => 'Recent customer text'],
        'status' => 'processed',
    ]);
    $pending = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'event_id' => 'message:wamid.pending',
        'event_type' => 'message',
        'payload' => ['kind' => 'message', 'body' => 'Pending customer text'],
        'status' => 'received',
    ]);
    $stalled = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'event_id' => 'message:wamid.stalled',
        'event_type' => 'message',
        'payload' => ['kind' => 'message', 'from' => '2348031234567', 'body' => 'Stalled customer content'],
        'status' => 'received',
    ]);
    $stalled->forceFill(['updated_at' => now()->subDays(31)])->saveQuietly();

    expect(app(WhatsAppConfig::class)->webhookPayloadRetentionDays())->toBe(30);
    Artisan::call('webhooks:redact-expired-payloads');

    expect($expired->fresh()->payload)->toBe(['redacted' => true])
        ->and($recent->fresh()->payload)->toBe(['kind' => 'message', 'body' => 'Recent customer text'])
        ->and($pending->fresh()->payload)->toBe(['kind' => 'message', 'body' => 'Pending customer text'])
        ->and($stalled->fresh()->payload)->toBe(['redacted' => true])
        ->and($stalled->fresh()->status)->toBe('failed')
        ->and($stalled->fresh()->processed_at)->not->toBeNull();
    $this->assertDatabaseHas('webhook_events', ['id' => $expired->getKey(), 'event_id' => 'message:wamid.expired']);

    Carbon::setTestNow();
});

it('redacts expired unresolved callback payloads while retaining callback data for reconciliation', function (): void {
    Carbon::setTestNow('2026-11-26 12:00:00');
    config(['whatsapp.webhook_payload_retention_days' => 30]);
    $business = Business::factory()->create();
    $event = WebhookEvent::query()->create([
        'business_id' => $business->getKey(),
        'provider' => 'whatsapp',
        'event_id' => 'status:wamid.never-arrived',
        'event_type' => 'status',
        'payload' => ['kind' => 'status', 'provider_message_id' => 'wamid.never-arrived'],
        'status' => 'awaiting_message',
    ]);
    $event->forceFill(['updated_at' => now()->subDays(31)])->saveQuietly();
    $messageEvent = MessageEvent::query()->create([
        'business_id' => $business->getKey(),
        'webhook_event_id' => $event->getKey(),
        'provider_message_id' => 'wamid.never-arrived',
        'event_type' => 'message.status',
        'status' => 'delivered',
        'occurred_at' => now()->subDays(31),
    ]);
    $recent = WebhookEvent::query()->create([
        'business_id' => $business->getKey(),
        'provider' => 'whatsapp',
        'event_id' => 'status:wamid.retryable',
        'event_type' => 'status',
        'payload' => ['kind' => 'status', 'provider_message_id' => 'wamid.retryable'],
        'status' => 'awaiting_message',
    ]);
    $recent->forceFill(['updated_at' => now()->subDays(2)])->saveQuietly();
    $pendingMessageEvent = MessageEvent::query()->create([
        'business_id' => $business->getKey(),
        'webhook_event_id' => $recent->getKey(),
        'provider_message_id' => 'wamid.retryable',
        'event_type' => 'message.status',
        'status' => 'delivered',
        'occurred_at' => now(),
    ]);

    $redacted = app(RedactExpiredWebhookPayloadsCommand::class)->execute();

    expect($redacted)->toBe(1)
        ->and($event->fresh()->payload)->toBe(['redacted' => true])
        ->and($event->fresh()->status)->toBe('awaiting_message')
        ->and($messageEvent->fresh()->provider_message_id)->toBe('wamid.never-arrived')
        ->and($recent->fresh()->payload)->toBe(['kind' => 'status', 'provider_message_id' => 'wamid.retryable'])
        ->and($pendingMessageEvent->fresh()->provider_message_id)->toBe('wamid.retryable');

    Carbon::setTestNow();
});

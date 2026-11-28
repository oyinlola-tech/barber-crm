<?php

use App\Jobs\WhatsApp\ProcessWhatsAppWebhookJob;
use App\Models\Business;
use App\Models\WebhookEvent;
use App\Services\Webhooks\Commands\RequeueStalledWhatsAppWebhookEventsCommand;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(LazilyRefreshDatabase::class);

it('requeues old received WhatsApp events and leaves recent or terminal events alone', function (): void {
    Queue::fake([ProcessWhatsAppWebhookJob::class]);
    config(['whatsapp.webhook_dispatch_recovery_minutes' => 5]);
    $business = Business::factory()->create();

    $stalled = WebhookEvent::query()->create([
        'business_id' => $business->getKey(),
        'provider' => 'whatsapp',
        'event_id' => 'message:wamid.dispatch-lost',
        'event_type' => 'message',
        'payload' => ['kind' => 'message', 'provider_message_id' => 'wamid.dispatch-lost'],
        'status' => 'received',
    ]);
    $stalled->forceFill(['updated_at' => now()->subMinutes(6)])->saveQuietly();
    $recent = WebhookEvent::query()->create([
        'business_id' => $business->getKey(),
        'provider' => 'whatsapp',
        'event_id' => 'message:wamid.recent',
        'event_type' => 'message',
        'payload' => ['kind' => 'message'],
        'status' => 'received',
    ]);
    $failed = WebhookEvent::query()->create([
        'business_id' => $business->getKey(),
        'provider' => 'whatsapp',
        'event_id' => 'message:wamid.failed-terminal',
        'event_type' => 'message',
        'payload' => ['redacted' => true],
        'status' => 'failed',
    ]);

    expect(app(RequeueStalledWhatsAppWebhookEventsCommand::class)->execute())->toBe(1)
        ->and($stalled->fresh()->updated_at->greaterThan(now()->subMinute()))->toBeTrue();
    Queue::assertPushed(ProcessWhatsAppWebhookJob::class, static fn (ProcessWhatsAppWebhookJob $job): bool => $job->webhookEventId === (int) $stalled->getKey());
    Queue::assertPushedTimes(ProcessWhatsAppWebhookJob::class, 1);
    expect($recent->fresh()->status)->toBe('received')
        ->and($failed->fresh()->status)->toBe('failed');
});

it('uses a stable unique queue key for each webhook processing job', function (): void {
    $first = new ProcessWhatsAppWebhookJob(42);
    $second = new ProcessWhatsAppWebhookJob(42);
    $differentEvent = new ProcessWhatsAppWebhookJob(43);

    expect($first->uniqueId())->toBe('whatsapp-webhook-event:42')
        ->and($second->uniqueId())->toBe($first->uniqueId())
        ->and($differentEvent->uniqueId())->not->toBe($first->uniqueId());
});

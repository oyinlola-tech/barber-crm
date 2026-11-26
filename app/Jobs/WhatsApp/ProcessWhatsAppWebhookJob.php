<?php

namespace App\Jobs\WhatsApp;

use App\Configs\WhatsAppConfig;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageEvent;
use App\Models\WebhookEvent;
use App\Models\WhatsAppConnection;
use App\Services\Automations\Commands\CancelCustomerFollowUpsCommand;
use App\Services\Consent\Commands\RecordConsentCommand;
use App\Utils\PhoneNumberNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessWhatsAppWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [15, 60, 300, 900];

    public function __construct(public int $webhookEventId) {}

    public function handle(
        PhoneNumberNormalizer $phoneNumbers,
        RecordConsentCommand $recordConsent,
        CancelCustomerFollowUpsCommand $cancelCustomerFollowUps,
        WhatsAppConfig $configuration,
    ): void {
        $processed = DB::transaction(function () use ($phoneNumbers, $recordConsent, $cancelCustomerFollowUps, $configuration): bool {
            $event = WebhookEvent::query()->lockForUpdate()->findOrFail($this->webhookEventId);
            if ($event->status === 'processed' || $event->business_id === null) {
                return true;
            }

            $payload = $event->payload ?? [];
            $connection = WhatsAppConnection::query()
                ->where('business_id', $event->business_id)
                ->where('phone_number_id', $payload['phone_number_id'] ?? null)
                ->first();
            if ($connection === null) {
                $event->update(['status' => 'unmatched_connection', 'processed_at' => now()]);

                return true;
            }

            if (($payload['kind'] ?? null) === 'message') {
                $this->processInboundMessage($event, $payload, $phoneNumbers, $recordConsent, $cancelCustomerFollowUps, $configuration);
            } elseif (($payload['kind'] ?? null) === 'status') {
                if (! $this->processDeliveryStatus($event, $payload)) {
                    $event->update(['status' => 'awaiting_message']);

                    return false;
                }
            }

            $event->update([
                'status' => 'processed',
                'payload' => ['kind' => $payload['kind'] ?? 'unknown', 'processed' => true],
                'processed_at' => now(),
            ]);

            return true;
        }, 3);

        if (! $processed) {
            $attempt = $this->attempts();
            if ($attempt < $this->tries) {
                $backoffIndex = min(max($attempt - 1, 0), count($this->backoff) - 1);
                $this->release($this->backoff[$backoffIndex]);

                return;
            }

            WebhookEvent::query()->whereKey($this->webhookEventId)->update([
                'status' => 'unmatched_message',
                'payload' => ['kind' => 'status', 'awaiting_local_message_exhausted' => true],
                'processed_at' => now(),
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function processInboundMessage(
        WebhookEvent $event,
        array $payload,
        PhoneNumberNormalizer $phoneNumbers,
        RecordConsentCommand $recordConsent,
        CancelCustomerFollowUpsCommand $cancelCustomerFollowUps,
        WhatsAppConfig $configuration,
    ): void {
        $rawNumber = $payload['from'] ?? null;
        $providerMessageId = $payload['provider_message_id'] ?? null;
        if (! is_string($rawNumber) || ! is_string($providerMessageId) || $providerMessageId === '') {
            return;
        }

        $normalizedPhone = $phoneNumbers->normalize($rawNumber);
        if ($normalizedPhone === null) {
            return;
        }

        $customer = Customer::query()
            ->where('business_id', $event->business_id)
            ->where('phone', $normalizedPhone)
            ->lockForUpdate()
            ->first();
        if ($customer === null) {
            return;
        }

        $conversation = Conversation::query()
            ->where('business_id', $event->business_id)
            ->where('customer_id', $customer->getKey())
            ->where('channel', 'whatsapp')
            ->lockForUpdate()
            ->first();
        if ($conversation === null) {
            $conversation = Conversation::query()->create([
                'business_id' => $event->business_id,
                'customer_id' => $customer->getKey(),
                'channel' => 'whatsapp',
                'status' => 'open',
            ]);
        }

        $occurredAt = $this->timestamp($payload['timestamp'] ?? null);
        $message = Message::query()->firstOrCreate([
            'business_id' => $event->business_id,
            'provider_message_id' => $providerMessageId,
        ], [
            'conversation_id' => $conversation->getKey(),
            'direction' => 'inbound',
            'type' => is_string($payload['type'] ?? null) ? $payload['type'] : 'unknown',
            'status' => 'received',
            'body' => is_string($payload['body'] ?? null) ? $payload['body'] : null,
            'payload' => null,
            'sent_at' => $occurredAt,
        ]);

        MessageEvent::query()->firstOrCreate([
            'webhook_event_id' => $event->getKey(),
        ], [
            'business_id' => $event->business_id,
            'message_id' => $message->getKey(),
            'event_type' => 'message.received',
            'status' => 'received',
            'occurred_at' => $occurredAt,
        ]);

        if ($message->wasRecentlyCreated) {
            $conversation->forceFill([
                'status' => 'open',
                'last_message_at' => $occurredAt,
                'last_customer_message_at' => $occurredAt,
                'unread_count' => $conversation->unread_count + 1,
            ])->save();

            $cancelCustomerFollowUps->execute((int) $event->business_id, (int) $customer->getKey());

            $body = $payload['body'] ?? null;
            if (is_string($body) && $this->isOptOut($body, $configuration)) {
                $recordConsent->execute((int) $event->business_id, (int) $customer->getKey(), 'whatsapp', false, 'whatsapp_keyword', null);
            }
        }
    }

    /** @param array<string, mixed> $payload */
    private function processDeliveryStatus(WebhookEvent $event, array $payload): bool
    {
        $providerMessageId = $payload['provider_message_id'] ?? null;
        $providerStatus = $payload['status'] ?? null;
        if (! is_string($providerMessageId) || ! is_string($providerStatus)) {
            return true;
        }

        $message = Message::query()
            ->where('business_id', $event->business_id)
            ->where('provider_message_id', $providerMessageId)
            ->lockForUpdate()
            ->first();
        $occurredAt = $this->timestamp($payload['timestamp'] ?? null);
        $mappedStatus = match ($providerStatus) {
            'sent' => 'sent',
            'delivered' => 'delivered',
            'read' => 'read',
            'failed' => 'failed',
            default => null,
        };

        MessageEvent::query()->firstOrCreate([
            'webhook_event_id' => $event->getKey(),
        ], [
            'business_id' => $event->business_id,
            'message_id' => $message?->getKey(),
            'event_type' => 'message.status',
            'status' => $mappedStatus,
            'provider_error_code' => is_string($payload['error_code'] ?? null) ? $payload['error_code'] : null,
            'occurred_at' => $occurredAt,
        ]);

        if ($message === null || $mappedStatus === null) {
            return $message !== null || $mappedStatus === null;
        }

        $currentRank = $this->statusRank((string) $message->status);
        $incomingRank = $this->statusRank($mappedStatus);
        $failureAfterSent = $message->status === 'sent' && $mappedStatus === 'failed';
        $sentAfterFailure = $message->status === 'failed' && $mappedStatus === 'sent';
        if ($incomingRank < $currentRank
            || ($incomingRank === $currentRank && $message->status !== $mappedStatus && ! $failureAfterSent)
            || $sentAfterFailure) {
            return true;
        }

        $attributes = ['status' => $mappedStatus];
        if ($mappedStatus === 'sent') {
            $attributes['sent_at'] = $message->sent_at ?? $occurredAt;
        } elseif ($mappedStatus === 'delivered') {
            $attributes['delivered_at'] = $message->delivered_at ?? $occurredAt;
        } elseif ($mappedStatus === 'read') {
            $attributes['read_at'] = $message->read_at ?? $occurredAt;
        } else {
            $attributes['failed_at'] = $occurredAt;
            $attributes['provider_error_code'] = $payload['error_code'] ?? null;
        }

        $message->forceFill($attributes)->save();

        return true;
    }

    private function isOptOut(string $body, WhatsAppConfig $configuration): bool
    {
        $keywords = $configuration->optOutKeywords();
        $normalizedBody = mb_strtoupper(trim($body));
        $normalizedBody = trim($normalizedBody, ' .!?,');
        foreach ($keywords as $keyword) {
            if ($normalizedBody === trim(mb_strtoupper(trim($keyword)), ' .!?,')) {
                return true;
            }
        }

        return false;
    }

    private function statusRank(string $status): int
    {
        return match ($status) {
            'sent' => 1,
            'delivered' => 2,
            'failed' => 2,
            'read' => 3,
            default => 0,
        };
    }

    private function timestamp(mixed $timestamp): CarbonImmutable
    {
        if (is_int($timestamp) || (is_string($timestamp) && ctype_digit($timestamp))) {
            return CarbonImmutable::createFromTimestampUTC((int) $timestamp);
        }

        return CarbonImmutable::now('UTC');
    }
}

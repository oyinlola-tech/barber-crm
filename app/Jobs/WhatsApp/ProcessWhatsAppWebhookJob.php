<?php

namespace App\Jobs\WhatsApp;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageEvent;
use App\Models\WebhookEvent;
use App\Models\WhatsAppConnection;
use App\Services\Automations\Commands\CancelCustomerFollowUpsCommand;
use App\Services\Consent\Commands\RecordConsentCommand;
use App\Services\Notifications\Commands\NotifyStaffOfInboundMessageCommand;
use App\Services\Settings\Queries\GetWhatsAppMessagingSettingsQuery;
use App\Services\WhatsApp\Commands\ApplyWhatsAppDeliveryStatusCommand;
use App\Utils\PhoneNumberNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessWhatsAppWebhookJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $uniqueFor = 86400;

    public array $backoff = [15, 60, 300, 900];

    public function __construct(public int $webhookEventId) {}

    public function uniqueId(): string
    {
        return 'whatsapp-webhook-event:'.$this->webhookEventId;
    }

    public function failed(Throwable $exception): void
    {
        WebhookEvent::query()->whereKey($this->webhookEventId)->update([
            'status' => 'failed',
            'payload' => ['redacted' => true],
            'processed_at' => now(),
        ]);
    }

    public function handle(
        PhoneNumberNormalizer $phoneNumbers,
        RecordConsentCommand $recordConsent,
        CancelCustomerFollowUpsCommand $cancelCustomerFollowUps,
        GetWhatsAppMessagingSettingsQuery $messagingSettings,
        ?NotifyStaffOfInboundMessageCommand $notifyStaff = null,
        ?ApplyWhatsAppDeliveryStatusCommand $applyDeliveryStatus = null,
    ): void {
        $notifyStaff ??= app(NotifyStaffOfInboundMessageCommand::class);
        $applyDeliveryStatus ??= app(ApplyWhatsAppDeliveryStatusCommand::class);
        $processed = DB::transaction(function () use ($phoneNumbers, $recordConsent, $cancelCustomerFollowUps, $messagingSettings, $notifyStaff, $applyDeliveryStatus): bool {
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
                $this->processInboundMessage($event, $payload, $phoneNumbers, $recordConsent, $cancelCustomerFollowUps, $messagingSettings->execute((int) $event->business_id), $notifyStaff);
            } elseif (($payload['kind'] ?? null) === 'status') {
                $statusProcessed = $this->processDeliveryStatus($event, $payload, $applyDeliveryStatus);
                if ($statusProcessed === false) {
                    $event->update(['status' => 'awaiting_message']);

                    return false;
                }

                if ($statusProcessed === null) {
                    $event->update([
                        'status' => 'failed',
                        'payload' => ['kind' => 'status', 'unsupported_status' => true],
                        'processed_at' => now(),
                    ]);

                    return true;
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
        array $optOutKeywords,
        NotifyStaffOfInboundMessageCommand $notifyStaff,
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
        $interactivePayload = array_filter([
            'interactive_reply_id' => is_string($payload['interactive_reply_id'] ?? null) ? $payload['interactive_reply_id'] : null,
            'interactive_reply_title' => is_string($payload['interactive_reply_title'] ?? null) ? $payload['interactive_reply_title'] : null,
        ], static fn (mixed $value): bool => is_string($value));
        $message = Message::query()->firstOrCreate([
            'business_id' => $event->business_id,
            'provider_message_id' => $providerMessageId,
        ], [
            'conversation_id' => $conversation->getKey(),
            'direction' => 'inbound',
            'type' => is_string($payload['type'] ?? null) ? $payload['type'] : 'unknown',
            'status' => 'received',
            'body' => is_string($payload['body'] ?? null) ? $payload['body'] : null,
            'payload' => $interactivePayload === [] ? null : $interactivePayload,
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
            $lastMessageAt = $conversation->last_message_at;
            $lastCustomerMessageAt = $conversation->last_customer_message_at;
            $conversation->forceFill([
                'status' => 'open',
                'last_message_at' => $lastMessageAt === null || $occurredAt->greaterThan($lastMessageAt) ? $occurredAt : $lastMessageAt,
                'last_customer_message_at' => $lastCustomerMessageAt === null || $occurredAt->greaterThan($lastCustomerMessageAt) ? $occurredAt : $lastCustomerMessageAt,
                'unread_count' => $conversation->unread_count + 1,
            ])->save();

            $cancelCustomerFollowUps->execute((int) $event->business_id, (int) $customer->getKey());
            $notifyStaff->execute((int) $event->business_id, (int) $message->getKey());

            $body = $payload['body'] ?? null;
            if (is_string($body) && $this->isOptOut($body, $optOutKeywords)) {
                $recordConsent->execute((int) $event->business_id, (int) $customer->getKey(), 'whatsapp', false, 'whatsapp_keyword', null, 'customer_follow_up');
            }
        }
    }

    /** @param array<string, mixed> $payload */
    private function processDeliveryStatus(WebhookEvent $event, array $payload, ApplyWhatsAppDeliveryStatusCommand $applyDeliveryStatus): ?bool
    {
        $providerMessageId = $payload['provider_message_id'] ?? null;
        $providerStatus = $payload['status'] ?? null;
        if (! is_string($providerMessageId) || ! is_string($providerStatus)) {
            return true;
        }

        $occurredAt = $this->timestamp($payload['timestamp'] ?? null);
        $mappedStatus = match ($providerStatus) {
            'sent' => 'sent',
            'delivered' => 'delivered',
            'read' => 'read',
            'failed' => 'failed',
            default => null,
        };

        if ($mappedStatus === null) {
            return null;
        }

        MessageEvent::query()->firstOrCreate([
            'webhook_event_id' => $event->getKey(),
        ], [
            'business_id' => $event->business_id,
            'provider_message_id' => $providerMessageId,
            'event_type' => 'message.status',
            'status' => $mappedStatus,
            'provider_error_code' => is_string($payload['error_code'] ?? null) ? $payload['error_code'] : null,
            'occurred_at' => $occurredAt,
        ]);

        $messageEvent = MessageEvent::query()->where('webhook_event_id', $event->getKey())->firstOrFail();

        return $applyDeliveryStatus->execute((int) $messageEvent->getKey());
    }

    /** @param list<string> $keywords */
    private function isOptOut(string $body, array $keywords): bool
    {
        $normalizedBody = mb_strtoupper(trim($body));
        $normalizedBody = trim($normalizedBody, ' .!?,');
        foreach ($keywords as $keyword) {
            if ($normalizedBody === trim(mb_strtoupper(trim($keyword)), ' .!?,')) {
                return true;
            }
        }

        return false;
    }

    private function timestamp(mixed $timestamp): CarbonImmutable
    {
        if (is_int($timestamp) || (is_string($timestamp) && ctype_digit($timestamp))) {
            return CarbonImmutable::createFromTimestampUTC((int) $timestamp);
        }

        return CarbonImmutable::now('UTC');
    }
}

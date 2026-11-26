<?php

namespace App\Services\Conversations\Commands;

use App\Configs\WhatsAppConfig;
use App\Enums\MessageStatus;
use App\Interfaces\Apis\MessagingProviderInterface;
use App\Mappers\Templates\WhatsAppTemplateDataMapper;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageEvent;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use App\Services\Consent\Queries\CustomerCanReceiveMessageQuery;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SendConversationTemplateReplyCommand
{
    public function __construct(
        private readonly MessagingProviderInterface $provider,
        private readonly CustomerCanReceiveMessageQuery $consent,
        private readonly WhatsAppTemplateDataMapper $templateData,
        private readonly WhatsAppConfig $whatsAppConfiguration,
        private readonly RecordAuditLogCommand $audit,
    ) {}

    public function execute(int $businessId, int $conversationId, int $actorId, int $templateId, string $idempotencyKey): Message
    {
        $actor = User::query()->findOrFail($actorId);
        abort_unless($actor->hasBusinessPermission($businessId, 'conversations.reply'), 403);

        try {
            $context = DB::transaction(function () use ($businessId, $conversationId, $templateId, $idempotencyKey): array {
                $existing = Message::query()
                    ->where('business_id', $businessId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing !== null) {
                    $this->assertSameRequest($existing, $conversationId, $templateId);

                    return ['message' => $existing, 'should_send' => false];
                }

                $conversation = Conversation::query()
                    ->where('business_id', $businessId)
                    ->where('channel', 'whatsapp')
                    ->with('customer')
                    ->lockForUpdate()
                    ->findOrFail($conversationId);
                $template = $this->approvedTemplate($businessId, $templateId);
                $this->connectedWhatsApp($businessId);
                $this->assertCustomerEligible($businessId, $conversation->customer);
                if ($this->hasUnresolvedTemplateDelivery($conversation)) {
                    throw ValidationException::withMessages(['template_id' => 'A previous template send has uncertain delivery. Reconcile it or wait for a new customer message before sending another template.']);
                }

                $business = Business::query()->findOrFail($businessId);
                $mapped = $this->templateData->map($template, $conversation->customer, $business);
                $message = Message::query()->create([
                    'business_id' => $businessId,
                    'conversation_id' => $conversationId,
                    'idempotency_key' => $idempotencyKey,
                    'direction' => 'outbound',
                    'type' => 'template',
                    'status' => MessageStatus::Pending->value,
                    'body' => $mapped['body'],
                    'payload' => [
                        'template_id' => $template->getKey(),
                        'template_name' => $template->name,
                        'language' => $template->language,
                    ],
                ]);

                return [
                    'message' => $message,
                    'should_send' => true,
                    'conversation' => $conversation,
                    'customer' => $conversation->customer,
                ];
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Message::query()
                ->where('business_id', $businessId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing === null) {
                throw $exception;
            }
            $this->assertSameRequest($existing, $conversationId, $templateId);

            return $existing;
        }

        /** @var Message $message */
        $message = $context['message'];
        if (! $context['should_send']) {
            return $message;
        }

        /** @var Conversation $conversation */
        $conversation = $context['conversation'];
        /** @var Customer $customer */
        $customer = $context['customer'];
        if (! $actor->fresh()?->hasBusinessPermission($businessId, 'conversations.reply')) {
            $message->update(['status' => MessageStatus::Cancelled->value]);

            return $message->refresh();
        }

        try {
            $this->assertCustomerEligible($businessId, $customer);
            if ($this->hasUnresolvedTemplateDelivery($conversation)) {
                throw ValidationException::withMessages(['template_id' => 'A previous template send has uncertain delivery. Reconcile it or wait for a new customer message before sending another template.']);
            }
            $template = $this->approvedTemplate($businessId, $templateId);
            $connection = $this->connectedWhatsApp($businessId);
            $business = Business::query()->findOrFail($businessId);
            $mapped = $this->templateData->map($template, $customer, $business);
        } catch (ValidationException) {
            $message->update(['status' => MessageStatus::Skipped->value]);

            return $message->refresh();
        }

        try {
            $response = $this->provider->sendTemplate(
                $connection,
                $customer->phone,
                $template->name,
                $template->language,
                ['components' => $mapped['components']],
            );
        } catch (ConnectionException) {
            return $this->markUncertain($message, $businessId, $actorId);
        } catch (RequestException $exception) {
            $isUncertain = $exception->response->status() >= 500;
            $status = $isUncertain ? MessageStatus::DeliveryUncertain : MessageStatus::Failed;
            $message->update(['status' => $status->value]);
            $this->recordEvent($message, $isUncertain ? 'message.send_uncertain' : 'message.send_failed', $status->value);
            $this->audit->execute($businessId, $actorId, $isUncertain ? 'message.send_uncertain' : 'message.send_failed', Message::class, (int) $message->getKey(), null, ['status' => $status->value]);

            return $message->refresh();
        } catch (Throwable) {
            return $this->markUncertain($message, $businessId, $actorId);
        }

        $providerMessageId = data_get($response, 'messages.0.id');
        if (! is_string($providerMessageId) || $providerMessageId === '') {
            return $this->markUncertain($message, $businessId, $actorId);
        }

        $message->update([
            'body' => $mapped['body'],
            'provider_message_id' => $providerMessageId,
            'status' => MessageStatus::Accepted->value,
            'sent_at' => now(),
        ]);
        $conversation->update(['last_message_at' => now()]);
        $this->recordEvent($message, 'message.accepted', MessageStatus::Accepted->value);
        $this->audit->execute($businessId, $actorId, 'message.accepted', Message::class, (int) $message->getKey(), null, ['status' => MessageStatus::Accepted->value]);

        return $message->refresh();
    }

    private function assertSameRequest(Message $message, int $conversationId, int $templateId): void
    {
        $payload = $message->payload ?? [];
        if ((int) $message->conversation_id !== $conversationId || (int) ($payload['template_id'] ?? 0) !== $templateId) {
            throw ValidationException::withMessages(['idempotency_key' => 'This request key has already been used for a different message.']);
        }
    }

    private function approvedTemplate(int $businessId, int $templateId): MessageTemplate
    {
        $template = MessageTemplate::query()
            ->where('business_id', $businessId)
            ->where('provider_status', 'APPROVED')
            ->where('is_enabled', true)
            ->whereNotNull('provider_template_id')
            ->find($templateId);
        if ($template === null) {
            throw ValidationException::withMessages(['template_id' => 'Select an enabled template approved by WhatsApp.']);
        }

        return $template;
    }

    private function connectedWhatsApp(int $businessId): WhatsAppConnection
    {
        $connection = WhatsAppConnection::query()
            ->where('business_id', $businessId)
            ->where('status', 'connected')
            ->first();
        if ($connection === null || $connection->access_token === null || $connection->phone_number_id === null
            || ! $this->whatsAppConfiguration->graphApiConfigured()) {
            throw ValidationException::withMessages(['template_id' => 'WhatsApp is not fully connected.']);
        }

        return $connection;
    }

    private function assertCustomerEligible(int $businessId, Customer $customer): void
    {
        if ($customer->archived_at !== null || $customer->status !== 'active') {
            throw ValidationException::withMessages(['template_id' => 'Archived or inactive customers cannot receive messages.']);
        }
        if (! $this->consent->execute($businessId, (int) $customer->getKey(), 'whatsapp')) {
            throw ValidationException::withMessages(['template_id' => 'A recorded WhatsApp consent is required before sending.']);
        }
    }

    private function hasUnresolvedTemplateDelivery(Conversation $conversation): bool
    {
        $uncertainMessage = $conversation->messages()
            ->where('direction', 'outbound')
            ->where('type', 'template')
            ->where('status', MessageStatus::DeliveryUncertain->value)
            ->latest('created_at')
            ->first();
        if ($uncertainMessage === null) {
            return false;
        }

        $latestInbound = $conversation->messages()
            ->where('direction', 'inbound')
            ->whereNotNull('sent_at')
            ->latest('sent_at')
            ->first();

        return $latestInbound?->sent_at === null || $latestInbound->sent_at->lessThanOrEqualTo($uncertainMessage->created_at);
    }

    private function markUncertain(Message $message, int $businessId, int $actorId): Message
    {
        $message->update(['status' => MessageStatus::DeliveryUncertain->value]);
        $this->recordEvent($message, 'message.send_uncertain', MessageStatus::DeliveryUncertain->value);
        $this->audit->execute($businessId, $actorId, 'message.send_uncertain', Message::class, (int) $message->getKey(), null, ['status' => MessageStatus::DeliveryUncertain->value]);

        return $message->refresh();
    }

    private function recordEvent(Message $message, string $eventType, string $status): void
    {
        MessageEvent::query()->create([
            'business_id' => $message->business_id,
            'message_id' => $message->getKey(),
            'event_type' => $eventType,
            'status' => $status,
            'occurred_at' => now(),
        ]);
    }
}

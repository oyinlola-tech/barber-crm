<?php

namespace App\Services\Conversations\Commands;

use App\Enums\WhatsAppMessageFeature;
use App\Errors\WhatsApp\WhatsAppConnectionUnavailable;
use App\Interfaces\Apis\MessagingProviderInterface;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageEvent;
use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use App\Services\Consent\Queries\CustomerCanReceiveMessageQuery;
use App\Services\Settings\Queries\GetWhatsAppMessageFeaturesQuery;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendConversationReplyCommand
{
    public function __construct(
        public MessagingProviderInterface $provider,
        public CustomerCanReceiveMessageQuery $consent,
        public RecordAuditLogCommand $audit,
        private readonly GetWhatsAppMessageFeaturesQuery $messageFeatures,
    ) {}

    /** @param list<string>|null $buttonTitles */
    public function execute(int $businessId, int $conversationId, int $actorId, string $body, string $idempotencyKey, ?array $buttonTitles = null): Message
    {
        $actor = User::query()->findOrFail($actorId);
        abort_unless($actor->hasBusinessPermission($businessId, 'conversations.reply'), 403);
        $this->assertManualRepliesEnabled($businessId);
        $buttons = $this->prepareButtons($buttonTitles, $idempotencyKey);

        try {
            $context = DB::transaction(function () use ($businessId, $conversationId, $body, $idempotencyKey, $buttons): array {
                $existing = Message::query()
                    ->where('business_id', $businessId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing !== null) {
                    $this->assertSameRequest($existing, $conversationId, $body, $buttons);

                    return ['message' => $existing, 'should_send' => false];
                }

                $conversation = Conversation::query()
                    ->where('business_id', $businessId)
                    ->with('customer')
                    ->lockForUpdate()
                    ->findOrFail($conversationId);
                $connection = WhatsAppConnection::query()
                    ->where('business_id', $businessId)
                    ->where('status', 'connected')
                    ->first();
                if ($connection === null) {
                    throw ValidationException::withMessages(['body' => 'WhatsApp is not connected.']);
                }

                $this->assertEligibleForFreeFormReply($businessId, $conversation);
                $uncertainReply = $conversation->messages()
                    ->where('direction', 'outbound')
                    ->whereIn('type', ['text', 'interactive'])
                    ->whereIn('status', ['pending', 'processing', 'delivery_uncertain'])
                    ->latest('created_at')
                    ->first();
                $latestInbound = $conversation->messages()
                    ->where('direction', 'inbound')
                    ->whereNotNull('sent_at')
                    ->latest('sent_at')
                    ->first();

                if ($uncertainReply !== null && ($latestInbound?->sent_at === null || $latestInbound->sent_at->lessThanOrEqualTo($uncertainReply->created_at))) {
                    throw ValidationException::withMessages(['body' => 'A previous reply has uncertain delivery. Wait for reconciliation or a new customer message before sending again.']);
                }

                return ['message' => Message::query()->create([
                    'business_id' => $businessId,
                    'conversation_id' => $conversation->getKey(),
                    'idempotency_key' => $idempotencyKey,
                    'direction' => 'outbound',
                    'type' => $buttons === [] ? 'text' : 'interactive',
                    'status' => 'pending',
                    'body' => trim($body),
                    'payload' => $buttons === [] ? null : ['buttons' => $buttons],
                ]), 'should_send' => true];
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Message::query()
                ->where('business_id', $businessId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing === null) {
                throw $exception;
            }
            $this->assertSameRequest($existing, $conversationId, $body, $buttons);

            return $existing;
        }

        /** @var Message $message */
        $message = $context['message'];
        if (! $context['should_send']) {
            return $message;
        }

        $conversation = Conversation::query()->where('business_id', $businessId)->with('customer')->findOrFail($conversationId);
        $connection = WhatsAppConnection::query()
            ->where('business_id', $businessId)
            ->where('status', 'connected')
            ->first();
        if ($connection === null) {
            $message->update(['status' => 'cancelled']);
            throw ValidationException::withMessages(['body' => 'WhatsApp is no longer connected.']);
        }

        try {
            $this->assertManualRepliesEnabled($businessId);
            $this->assertEligibleForFreeFormReply($businessId, $conversation);
        } catch (ValidationException $exception) {
            $message->update(['status' => 'skipped']);
            throw $exception;
        }

        try {
            $response = $buttons === []
                ? $this->provider->sendText($connection, $conversation->customer->phone, trim($message->body))
                : $this->provider->sendInteractiveButtons($connection, $conversation->customer->phone, trim($message->body), $buttons);
            $providerMessageId = data_get($response, 'messages.0.id');
            if (! is_string($providerMessageId) || $providerMessageId === '') {
                $message->update(['status' => 'delivery_uncertain']);
                $this->recordEvent($message, 'message.send_uncertain', 'delivery_uncertain');
                $this->audit->execute($businessId, $actorId, 'message.send_uncertain', Message::class, (int) $message->getKey(), null, ['status' => 'delivery_uncertain']);

                return $message->refresh();
            }

            $message->update([
                'provider_message_id' => $providerMessageId,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            $this->recordEvent($message, 'message.accepted', 'sent');
            $conversation->update(['last_message_at' => now()]);
            $this->audit->execute($businessId, $actorId, 'message.sent', Message::class, (int) $message->getKey(), null, ['status' => 'sent']);

            return $message->refresh();
        } catch (WhatsAppConnectionUnavailable) {
            $message->update(['status' => 'failed']);
            $this->recordEvent($message, 'message.send_failed', 'failed');
            $this->audit->execute($businessId, $actorId, 'message.send_failed', Message::class, (int) $message->getKey(), null, ['status' => 'failed']);

            return $message->refresh();
        } catch (ConnectionException) {
            $message->update(['status' => 'delivery_uncertain']);
            $this->recordEvent($message, 'message.send_uncertain', 'delivery_uncertain');
            $this->audit->execute($businessId, $actorId, 'message.send_uncertain', Message::class, (int) $message->getKey(), null, ['status' => 'delivery_uncertain']);

            return $message->refresh();
        } catch (RequestException $exception) {
            $status = $exception->response->status();
            $message->update(['status' => $status >= 500 ? 'delivery_uncertain' : 'failed']);
            $this->recordEvent($message, $status >= 500 ? 'message.send_uncertain' : 'message.send_failed', $status >= 500 ? 'delivery_uncertain' : 'failed');
            $this->audit->execute($businessId, $actorId, $status >= 500 ? 'message.send_uncertain' : 'message.send_failed', Message::class, (int) $message->getKey(), null, ['status' => $status >= 500 ? 'delivery_uncertain' : 'failed']);

            return $message->refresh();
        }
    }

    /** @param list<array{id:string,title:string}> $buttons */
    private function assertSameRequest(Message $message, int $conversationId, string $body, array $buttons): void
    {
        if ((int) $message->conversation_id !== $conversationId
            || $message->type !== ($buttons === [] ? 'text' : 'interactive')
            || trim((string) $message->body) !== trim($body)
            || ($message->payload['buttons'] ?? []) !== $buttons) {
            throw ValidationException::withMessages(['idempotency_key' => 'This request key has already been used for a different message.']);
        }
    }

    /** @param list<string>|null $buttonTitles @return list<array{id:string,title:string}> */
    private function prepareButtons(?array $buttonTitles, string $idempotencyKey): array
    {
        if ($buttonTitles === null || $buttonTitles === []) {
            return [];
        }
        $buttonTitles = array_map('trim', $buttonTitles);
        if (count($buttonTitles) > 3
            || in_array('', $buttonTitles, true)
            || array_any($buttonTitles, static fn (string $title): bool => mb_strlen($title) > 20)
            || count(array_unique(array_map('mb_strtolower', $buttonTitles))) !== count($buttonTitles)) {
            throw ValidationException::withMessages(['buttons' => 'Choose up to three options with unique labels.']);
        }

        return array_map(
            static fn (string $title, int $index): array => [
                'id' => 'choice_'.substr(hash('sha256', $idempotencyKey.':'.$index), 0, 24),
                'title' => trim($title),
            ],
            $buttonTitles,
            array_keys($buttonTitles),
        );
    }

    private function assertEligibleForFreeFormReply(int $businessId, Conversation $conversation): void
    {
        if (! $this->consent->execute($businessId, (int) $conversation->customer_id, 'whatsapp')) {
            throw ValidationException::withMessages(['body' => 'A recorded WhatsApp consent is required before sending.']);
        }

        $lastInbound = $conversation->messages()
            ->where('direction', 'inbound')
            ->whereNotNull('sent_at')
            ->latest('sent_at')
            ->first();
        if ($lastInbound === null || $lastInbound->sent_at->lt(now()->subHours(24))) {
            throw ValidationException::withMessages(['body' => 'A free-form reply is only available within 24 hours of the customer’s latest message.']);
        }

        if ($conversation->customer->archived_at !== null || $conversation->customer->status !== 'active') {
            throw ValidationException::withMessages(['body' => 'Archived or inactive customers cannot receive messages.']);
        }
    }

    private function assertManualRepliesEnabled(int $businessId): void
    {
        if (! $this->messageFeatures->isEnabled($businessId, WhatsAppMessageFeature::ManualReplies)) {
            throw ValidationException::withMessages(['body' => 'WhatsApp staff replies are turned off in messaging settings.']);
        }
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

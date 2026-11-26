<?php

namespace App\Services\Conversations\Commands;

use App\Interfaces\Apis\MessagingProviderInterface;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageEvent;
use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use App\Services\Consent\Queries\CustomerCanReceiveMessageQuery;
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
    ) {}

    public function execute(int $businessId, int $conversationId, int $actorId, string $body): Message
    {
        $actor = User::query()->findOrFail($actorId);
        abort_unless($actor->hasBusinessPermission($businessId, 'conversations.reply'), 403);

        $message = DB::transaction(function () use ($businessId, $conversationId, $body): Message {
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

            return Message::query()->create([
                'business_id' => $businessId,
                'conversation_id' => $conversation->getKey(),
                'direction' => 'outbound',
                'type' => 'text',
                'status' => 'pending',
                'body' => trim($body),
            ]);
        });

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
            $this->assertEligibleForFreeFormReply($businessId, $conversation);
        } catch (ValidationException $exception) {
            $message->update(['status' => 'skipped']);
            throw $exception;
        }

        try {
            $response = $this->provider->sendText($connection, $conversation->customer->phone, trim($message->body));
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

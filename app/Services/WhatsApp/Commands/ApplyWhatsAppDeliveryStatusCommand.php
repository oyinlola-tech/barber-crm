<?php

namespace App\Services\WhatsApp\Commands;

use App\Models\Message;
use App\Models\MessageEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ApplyWhatsAppDeliveryStatusCommand
{
    public function execute(int $messageEventId): bool
    {
        return DB::transaction(function () use ($messageEventId): bool {
            $event = MessageEvent::query()->lockForUpdate()->findOrFail($messageEventId);
            if ($event->message_id !== null) {
                return true;
            }

            if ($event->event_type !== 'message.status'
                || ! is_string($event->provider_message_id)
                || $event->provider_message_id === '') {
                return true;
            }

            if (! in_array($event->status, ['sent', 'delivered', 'read', 'failed'], true)) {
                return true;
            }

            $message = Message::query()
                ->where('business_id', $event->business_id)
                ->where('provider_message_id', $event->provider_message_id)
                ->where('direction', 'outbound')
                ->lockForUpdate()
                ->first();

            if ($message === null) {
                return false;
            }

            $event->forceFill(['message_id' => $message->getKey()])->save();
            $mappedStatus = (string) $event->status;

            $occurredAt = $event->occurred_at ?? Carbon::now('UTC');
            $currentRank = $this->statusRank((string) $message->status);
            $incomingRank = $this->statusRank($mappedStatus);
            $sentAfterFailure = $message->status === 'failed' && $mappedStatus === 'sent';
            $staleFailureAfterSent = $message->status === 'sent'
                && $mappedStatus === 'failed'
                && $message->sent_at !== null
                && $occurredAt->lessThan($message->sent_at);

            if ($staleFailureAfterSent
                || $incomingRank < $currentRank
                || ($incomingRank === $currentRank && $message->status !== $mappedStatus)
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
                $attributes['provider_error_code'] = $event->provider_error_code;
            }

            $message->forceFill($attributes)->save();

            return true;
        }, 3);
    }

    private function statusRank(string $status): int
    {
        return match ($status) {
            'sent' => 1,
            'delivered', 'failed' => 2,
            'read' => 3,
            default => 0,
        };
    }
}

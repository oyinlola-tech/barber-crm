<?php

namespace App\Services\Notifications\Commands;

use App\Models\Business;
use App\Models\Message;
use App\Models\ScheduledMessage;
use App\Notifications\MessageDeliveryUncertainNotification;
use Ramsey\Uuid\Uuid;

class NotifyStaffOfUncertainMessageCommand
{
    public function execute(int $businessId, int $messageId, string $resourceType): void
    {
        if (! in_array($resourceType, ['message', 'scheduled_message'], true)) {
            return;
        }

        $query = $resourceType === 'message'
            ? Message::query()->where('business_id', $businessId)
            : ScheduledMessage::query()->where('business_id', $businessId);

        $message = $query->find($messageId);
        if ($message === null) {
            return;
        }

        $business = Business::query()->find($businessId);
        if ($business === null) {
            return;
        }

        $url = $resourceType === 'message' && $message instanceof Message
            ? '/inbox/'.$message->conversation_id
            : '/messages/scheduled';
        $requiredPermission = $resourceType === 'message' ? 'conversations.view' : 'messages.view';

        $business->members()->wherePivot('status', 'active')->get()
            ->filter(fn ($member): bool => $member->hasBusinessPermission($businessId, $requiredPermission))
            ->each(function ($member) use ($businessId, $resourceType, $messageId, $url): void {
                $notificationId = Uuid::uuid5(Uuid::NAMESPACE_URL, implode(':', [
                    'uncertain-message',
                    $businessId,
                    $resourceType,
                    $messageId,
                    $member->getKey(),
                ]))->toString();

                if ($member->notifications()->whereKey($notificationId)->exists()) {
                    return;
                }

                $member->notify(new MessageDeliveryUncertainNotification($notificationId, $url));
            });
    }
}

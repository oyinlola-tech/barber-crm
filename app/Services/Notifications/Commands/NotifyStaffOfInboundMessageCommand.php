<?php

namespace App\Services\Notifications\Commands;

use App\Models\Business;
use App\Models\Message;
use App\Notifications\CustomerMessageReceivedNotification;
use Ramsey\Uuid\Uuid;

class NotifyStaffOfInboundMessageCommand
{
    public function execute(int $businessId, int $messageId): void
    {
        $message = Message::query()
            ->where('business_id', $businessId)
            ->where('direction', 'inbound')
            ->with('conversation:id,business_id,customer_id')
            ->findOrFail($messageId);
        $conversation = $message->conversation;
        if ($conversation === null || (int) $conversation->business_id !== $businessId) {
            return;
        }

        $business = Business::query()->find($businessId);
        if ($business === null) {
            return;
        }

        $business->members()->wherePivot('status', 'active')->get()
            ->filter(fn ($member): bool => $member->hasBusinessPermission($businessId, 'conversations.view'))
            ->each(function ($member) use ($businessId, $message, $conversation): void {
                $notificationId = Uuid::uuid5(Uuid::NAMESPACE_URL, implode(':', [
                    'customer-message',
                    $businessId,
                    $message->getKey(),
                    $member->getKey(),
                ]))->toString();
                if ($member->notifications()->whereKey($notificationId)->exists()) {
                    return;
                }

                $member->notify(new CustomerMessageReceivedNotification($notificationId, (int) $conversation->getKey()));
            });
    }
}

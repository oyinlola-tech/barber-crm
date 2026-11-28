<?php

namespace App\Services\Notifications\Commands;

use App\Interfaces\Repositories\Audit\ActivityDigestRepositoryInterface;
use App\Notifications\DailyActivityDigestNotification;
use App\Services\Audit\Queries\GetDailyActivityDigestQuery;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Ramsey\Uuid\Uuid;

class DeliverDailyActivityDigestCommand
{
    public function __construct(
        private readonly ActivityDigestRepositoryInterface $repository,
        private readonly GetDailyActivityDigestQuery $query,
    ) {}

    public function execute(int $businessId, string $date): void
    {
        $business = $this->repository->activeBusiness();
        if ($business === null || (int) $business->getKey() !== $businessId) {
            return;
        }

        $timezone = $business->timezone ?: (string) config('app.timezone');
        $digest = $this->query->execute($businessId, $date, $timezone);
        if ($digest->totalEvents === 0) {
            return;
        }

        $recipients = $this->repository->digestRecipients($businessId);
        foreach ($recipients as $recipient) {
            $notificationId = Uuid::uuid5(Uuid::NAMESPACE_URL, implode(':', [
                'daily-activity-digest',
                $businessId,
                $date,
                $recipient->getKey(),
            ]))->toString();

            $notification = new DailyActivityDigestNotification($notificationId, $digest);
            $storedNotification = $recipient->notifications()->whereKey($notificationId)->first();

            if ($storedNotification === null) {
                Notification::sendNow($recipient, $notification, ['database']);
                $storedNotification = $recipient->notifications()->whereKey($notificationId)->first();
            }

            if (! $storedNotification instanceof DatabaseNotification
                || isset($storedNotification->data['_delivery']['email_sent_at'])) {
                continue;
            }

            Notification::sendNow($recipient, $notification, ['mail']);

            $data = $storedNotification->data;
            $data['_delivery'] = ['email_sent_at' => now()->toIso8601String()];
            $storedNotification->forceFill(['data' => $data])->save();
        }
    }
}

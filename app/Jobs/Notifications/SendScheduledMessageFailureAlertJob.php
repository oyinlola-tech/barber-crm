<?php

namespace App\Jobs\Notifications;

use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\ScheduledMessageFailedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SendScheduledMessageFailureAlertJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    public int $uniqueFor = 86400;

    public function __construct(
        public readonly int $userId,
        public readonly int $businessId,
        public readonly string $notificationId,
    ) {}

    public function uniqueId(): string
    {
        return implode(':', [$this->businessId, $this->userId, $this->notificationId]);
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        if ($user === null
            || ! $user->businesses()->whereKey($this->businessId)->wherePivot('status', 'active')->exists()
            || ! $user->hasBusinessPermission($this->businessId, 'messages.view')) {
            return;
        }

        Notification::sendNow(
            $user,
            new ScheduledMessageFailedNotification($this->notificationId),
            ['mail', WebPushChannel::class],
        );
    }
}

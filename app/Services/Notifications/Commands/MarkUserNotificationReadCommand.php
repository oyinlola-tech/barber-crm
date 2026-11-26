<?php

namespace App\Services\Notifications\Commands;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class MarkUserNotificationReadCommand
{
    public function execute(User $user, string $notificationId): void
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->whereKey($notificationId)->firstOrFail();
        $notification->markAsRead();
    }
}

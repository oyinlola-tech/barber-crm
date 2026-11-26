<?php

namespace App\Services\Notifications\Commands;

use App\Models\User;

class MarkAllUserNotificationsReadCommand
{
    public function execute(User $user): int
    {
        return $user->unreadNotifications()->update([
            'read_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

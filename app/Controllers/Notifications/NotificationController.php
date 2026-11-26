<?php

namespace App\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Services\Notifications\Commands\MarkAllUserNotificationsReadCommand;
use App\Services\Notifications\Commands\MarkUserNotificationReadCommand;
use App\Services\Notifications\Queries\ListUserNotificationsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request, ListUserNotificationsQuery $query): Response
    {
        $user = $request->user();

        return Inertia::render('notifications/index', [
            'notifications' => $query->execute($user),
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $notification, MarkUserNotificationReadCommand $command): RedirectResponse
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $notification) === 1, 404);
        $command->execute($request->user(), $notification);

        return to_route('notifications.index')->with('success', 'Notification marked as read.');
    }

    public function markAllRead(Request $request, MarkAllUserNotificationsReadCommand $command): RedirectResponse
    {
        $command->execute($request->user());

        return to_route('notifications.index')->with('success', 'Notifications marked as read.');
    }
}

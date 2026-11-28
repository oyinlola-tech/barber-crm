<?php

namespace App\Notifications\Channels;

use App\Configs\WebPushConfig;
use App\Jobs\Notifications\SendWebPushNotificationJob;
use App\Models\User;
use App\Notifications\Contracts\WebPushNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class WebPushChannel
{
    public function __construct(private readonly WebPushConfig $config) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User
            || ! $notification instanceof WebPushNotification
            || ! $this->config->isConfigured()) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);
        $url = $payload['url'];
        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            $payload['url'] = '/dashboard';
        }
        $payload['tag'] = (string) Str::uuid();

        SendWebPushNotificationJob::dispatch(
            (int) $notifiable->getKey(),
            $notification->webPushPermission(),
            $payload,
        )->afterCommit();
    }
}

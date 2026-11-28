<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Contracts\WebPushNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VisitRecordedNotification extends Notification implements WebPushNotification
{
    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', WebPushChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A completed customer visit was recorded')
            ->view('mail.notifications.visit-recorded', [
                'url' => rtrim((string) config('app.url'), '/').'/dashboard',
            ]);
    }

    /** @return array{title:string,body:string,url:string} */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Customer visit recorded',
            'body' => 'A staff member updated completed visit history.',
            'url' => '/dashboard',
        ];
    }

    public function webPushPermission(): string
    {
        return 'dashboard.view';
    }

    /** @return array{title:string,message:string,url:string} */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Customer visit recorded',
            'message' => 'A staff member recorded a completed customer visit.',
            'url' => '/dashboard',
        ];
    }
}

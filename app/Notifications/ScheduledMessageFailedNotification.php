<?php

namespace App\Notifications;

use App\Notifications\Contracts\WebPushNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduledMessageFailedNotification extends Notification implements WebPushNotification
{
    public function __construct(string $id)
    {
        $this->id = $id;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A WhatsApp follow-up failed')
            ->view('mail.notifications.scheduled-message-failed', [
                'url' => rtrim((string) config('app.url'), '/').'/messages/scheduled',
            ]);
    }

    /** @return array{title:string,message:string,url:string} */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'A WhatsApp follow-up failed',
            'message' => 'A scheduled WhatsApp follow-up could not be sent. Review its status before retrying.',
            'url' => '/messages/scheduled',
        ];
    }

    /** @return array{title:string,body:string,url:string} */
    public function toWebPush(object $notifiable): array
    {
        $notification = $this->toArray($notifiable);

        return [
            'title' => $notification['title'],
            'body' => $notification['message'],
            'url' => $notification['url'],
        ];
    }

    public function webPushPermission(): string
    {
        return 'messages.view';
    }
}

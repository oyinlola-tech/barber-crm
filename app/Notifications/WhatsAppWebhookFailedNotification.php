<?php

namespace App\Notifications;

use App\Notifications\Contracts\WebPushNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WhatsAppWebhookFailedNotification extends Notification implements WebPushNotification
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
            ->subject('WhatsApp webhook processing needs attention')
            ->view('mail.notifications.whatsapp-webhook-failed', [
                'url' => rtrim((string) config('app.url'), '/').'/operations/health',
            ]);
    }

    /** @return array{title:string,message:string,url:string} */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'WhatsApp webhook processing failed',
            'message' => 'An incoming WhatsApp event could not be processed after retries. Review system health and the inbox.',
            'url' => '/operations/health',
        ];
    }

    /** @return array{title:string,body:string,url:string} */
    public function toWebPush(object $notifiable): array
    {
        $notification = $this->toArray($notifiable);

        return ['title' => $notification['title'], 'body' => $notification['message'], 'url' => $notification['url']];
    }

    public function webPushPermission(): string
    {
        return 'system.health.view';
    }
}

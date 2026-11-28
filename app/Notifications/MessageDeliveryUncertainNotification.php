<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Contracts\WebPushNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MessageDeliveryUncertainNotification extends Notification implements WebPushNotification
{
    public function __construct(string $id, private readonly string $url)
    {
        $this->id = $id;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', WebPushChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A WhatsApp delivery needs review')
            ->view('mail.notifications.message-delivery-uncertain', [
                'url' => rtrim((string) config('app.url'), '/').$this->url,
            ]);
    }

    /** @return array{title:string,body:string,url:string} */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'WhatsApp delivery needs review',
            'body' => 'A message has an uncertain delivery outcome. Check its history before retrying.',
            'url' => $this->url,
        ];
    }

    public function webPushPermission(): string
    {
        return str_starts_with($this->url, '/inbox/') ? 'conversations.view' : 'messages.view';
    }

    /** @return array{title:string,message:string,url:string} */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'WhatsApp delivery needs review',
            'message' => 'A WhatsApp message has an uncertain delivery outcome. Check its history before retrying.',
            'url' => $this->url,
        ];
    }
}

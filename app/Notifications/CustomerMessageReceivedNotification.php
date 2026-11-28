<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Contracts\WebPushNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerMessageReceivedNotification extends Notification implements WebPushNotification
{
    use Queueable;

    private readonly int $conversationId;

    public function __construct(string $id, int $conversationId)
    {
        $this->id = $id;
        $this->conversationId = $conversationId;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', WebPushChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A customer sent a WhatsApp message')
            ->view('mail.notifications.customer-message', [
                'url' => rtrim((string) config('app.url'), '/').'/inbox/'.$this->conversationId,
            ]);
    }

    /** @return array{title:string,body:string,url:string} */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'New WhatsApp message',
            'body' => 'A customer sent a message. Open your inbox to respond.',
            'url' => '/inbox/'.$this->conversationId,
        ];
    }

    public function webPushPermission(): string
    {
        return 'conversations.view';
    }

    /** @return array{title:string,message:string,url:string,conversation_id:int} */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New WhatsApp message',
            'message' => 'A customer sent a message. Open the inbox to respond.',
            'url' => '/inbox/'.$this->conversationId,
            'conversation_id' => $this->conversationId,
        ];
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerMessageReceivedNotification extends Notification
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
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A customer sent a WhatsApp message')
            ->view('mail.notifications.customer-message', [
                'url' => rtrim((string) config('app.url'), '/').'/inbox/'.$this->conversationId,
            ]);
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

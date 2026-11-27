<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class MessageDeliveryUncertainNotification extends Notification
{
    public function __construct(string $id, private readonly string $url)
    {
        $this->id = $id;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
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

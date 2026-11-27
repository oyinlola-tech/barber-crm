<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class MessageDeliveryUncertainNotification extends Notification
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

    /** @return array{title:string,message:string,url:string} */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'WhatsApp delivery needs review',
            'message' => 'A scheduled message has an uncertain delivery outcome. Check message history before retrying.',
            'url' => '/messages/scheduled',
        ];
    }
}

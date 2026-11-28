<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffAccessChangedNotification extends Notification
{
    public function __construct(
        string $notificationId,
        private readonly string $staffName,
        private readonly string $summary,
        private readonly string $url,
    ) {
        $this->id = $notificationId;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Staff access changed')
            ->view('mail.notifications.staff-access-changed', [
                'staffName' => $this->staffName,
                'summary' => $this->summary,
                'url' => rtrim((string) config('app.url'), '/').$this->url,
            ]);
    }

    /** @return array{id:string,title:string,message:string,url:string} */
    public function toArray(object $notifiable): array
    {
        return [
            'id' => $this->id,
            'title' => 'Staff access changed',
            'message' => $this->summary,
            'url' => $this->url,
        ];
    }
}

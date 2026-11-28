<?php

namespace App\Notifications;

use App\Dtos\Audit\DailyActivityDigestDto;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class DailyActivityDigestNotification extends Notification
{
    public function __construct(
        string $notificationId,
        private readonly DailyActivityDigestDto $digest,
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
            ->subject("Daily activity digest for {$this->digest->date}")
            ->view('mail.notifications.daily-activity-digest', [
                'businessName' => $this->digest->businessName,
                'date' => $this->digest->date,
                'totalEvents' => $this->digest->totalEvents,
                'actions' => collect($this->digest->actionCounts)
                    ->map(fn (int $count, string $action): array => [
                        'label' => Str::headline(str_replace('.', ' ', $action)),
                        'count' => $count,
                    ])->values()->all(),
                'url' => rtrim((string) config('app.url'), '/').'/operations/audit',
            ]);
    }

    /** @return array{id:string,title:string,message:string,url:string} */
    public function toArray(object $notifiable): array
    {
        return [
            'id' => $this->id,
            'title' => 'Daily activity digest',
            'message' => "{$this->digest->totalEvents} recorded business activities on {$this->digest->date}.",
            'url' => '/operations/audit',
        ];
    }
}

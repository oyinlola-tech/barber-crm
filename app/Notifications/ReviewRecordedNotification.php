<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Contracts\WebPushNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewRecordedNotification extends Notification implements ShouldQueue, WebPushNotification
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
            ->subject('New customer feedback is ready to review')
            ->view('mail.notifications.review-recorded', [
                'url' => rtrim((string) config('app.url'), '/').'/reviews',
            ]);
    }

    /** @return array{title:string,body:string,url:string} */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Customer feedback recorded',
            'body' => 'New feedback is ready for an authorized staff member to review.',
            'url' => '/reviews',
        ];
    }

    public function webPushPermission(): string
    {
        return 'reviews.view';
    }

    /** @return array{title:string,message:string,url:string} */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Customer feedback recorded',
            'message' => 'New feedback is ready for an authorized staff member to review.',
            'url' => '/reviews',
        ];
    }
}

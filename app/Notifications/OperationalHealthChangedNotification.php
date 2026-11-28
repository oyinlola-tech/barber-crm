<?php

namespace App\Notifications;

use App\Notifications\Contracts\WebPushNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class OperationalHealthChangedNotification extends Notification implements WebPushNotification
{
    /** @param list<array{component:string,previousStatus:?string,status:string}> $changes */
    public function __construct(
        string $notificationId,
        private readonly string $businessName,
        private readonly array $changes,
    ) {
        $this->id = $notificationId;
    }

    /** @return list<string|class-string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $recovered = collect($this->changes)->every(static fn (array $change): bool => $change['status'] === 'healthy');

        return (new MailMessage)
            ->subject($recovered ? 'Barber CRM system recovered' : 'Barber CRM system health needs attention')
            ->view('mail.notifications.operational-health', [
                'businessName' => $this->businessName,
                'changes' => collect($this->changes)->map(static fn (array $change): array => [
                    'component' => Str::headline($change['component']),
                    'status' => Str::headline($change['status']),
                    'previousStatus' => $change['previousStatus'] === null ? null : Str::headline($change['previousStatus']),
                ])->all(),
                'recovered' => $recovered,
                'url' => rtrim((string) config('app.url'), '/').'/operations/health',
            ]);
    }

    /** @return array{title:string,message:string,url:string} */
    public function toArray(object $notifiable): array
    {
        $recovered = collect($this->changes)->every(static fn (array $change): bool => $change['status'] === 'healthy');
        $summary = collect($this->changes)->map(static fn (array $change): string => Str::headline($change['component']).' is '.Str::headline($change['status']))->implode('; ');

        return [
            'title' => $recovered ? 'System health recovered' : 'System health needs attention',
            'message' => $summary.'.',
            'url' => '/operations/health',
        ];
    }

    /** @return array{title:string,body:string,url:string} */
    public function toWebPush(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return ['title' => $data['title'], 'body' => $data['message'], 'url' => $data['url']];
    }

    public function webPushPermission(): string
    {
        return 'system.health.view';
    }
}

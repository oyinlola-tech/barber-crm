<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerReturnIntentionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $customerId,
        private readonly string $customerName,
        private readonly string $customerPhone,
        private readonly string $intendedReturnDate,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A customer shared when they plan to return')
            ->view('mail.notifications.customer-return-intention', [
                'customerName' => $this->customerName,
                'customerPhone' => $this->customerPhone,
                'intendedReturnDate' => $this->intendedReturnDate,
                'url' => rtrim((string) config('app.url'), '/').'/customers/'.$this->customerId,
            ]);
    }

    /** @return array{title:string,message:string,url:string,customer_id:int,intended_return_date:string} */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Customer plans to return',
            'message' => $this->customerName.' shared a planned return date of '.$this->intendedReturnDate.'.',
            'url' => '/customers/'.$this->customerId,
            'customer_id' => $this->customerId,
            'intended_return_date' => $this->intendedReturnDate,
        ];
    }
}

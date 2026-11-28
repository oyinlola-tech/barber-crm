<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OwnerTemporaryAccess extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $businessName,
        public string $temporaryPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your temporary sign-in for {$this->businessName}",
            tags: ['owner-provisioning'],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.auth.owner-temporary-access');
    }
}

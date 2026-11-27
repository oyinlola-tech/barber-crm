<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $invitationUrl, public string $businessName) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your secure invitation to {$this->businessName}",
            tags: ['staff-invitation'],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.staff.invitation');
    }
}

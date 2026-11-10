<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VerifyEmailCode extends Mailable
{
    public function __construct(public string $code, public ?string $firstName = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Cyrcle verification code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.verify-email-code');
    }
}

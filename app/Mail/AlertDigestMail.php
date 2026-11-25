<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class AlertDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Collection $alerts)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('[AfyaWatch] Daily digest: %d open alert%s', $this->alerts->count(), $this->alerts->count() === 1 ? '' : 's'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.digest',
            with: ['alerts' => $this->alerts, 'dashboardUrl' => route('dashboard')],
        );
    }
}

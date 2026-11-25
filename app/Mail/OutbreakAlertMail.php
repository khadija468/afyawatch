<?php

namespace App\Mail;

use App\Models\Alert;
use App\Support\Week;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OutbreakAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Alert $alert)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                '[AfyaWatch] %s: %s in %s, %s',
                strtoupper($this->alert->severity),
                $this->alert->condition->name,
                $this->alert->region->name,
                Week::label($this->alert->week_start),
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.alert',
            with: [
                'alert' => $this->alert,
                'weekLabel' => Week::label($this->alert->week_start),
                'dashboardUrl' => route('dashboard', [
                    'condition_id' => $this->alert->condition_id,
                    'region_id' => $this->alert->region_id,
                ]),
            ],
        );
    }
}

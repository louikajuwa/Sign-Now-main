<?php

namespace App\Mail;

use App\Models\Document;
use App\Models\Recipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SigningInvite extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Document $document,
        public Recipient $recipient,
        public string $signUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Document Signing Request — ' . $this->document->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signing-invite',
            with: [
                'docTitle' => $this->document->title,
                'recipientEmail' => $this->recipient->email,
                'signUrl' => $this->signUrl,
            ],
        );
    }
}

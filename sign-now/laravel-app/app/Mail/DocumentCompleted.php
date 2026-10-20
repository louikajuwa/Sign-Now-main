<?php

namespace App\Mail;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentCompleted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Document $document,
        public string $recipientEmail,
        public ?string $pdfPath = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Document Completed — ' . $this->document->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document-completed',
            with: [
                'docTitle' => $this->document->title,
                'recipientEmail' => $this->recipientEmail,
            ],
        );
    }

    public function attachments(): array
    {
        if ($this->pdfPath && file_exists($this->pdfPath)) {
            return [
                Attachment::fromPath($this->pdfPath)
                    ->as(($this->document->title ?: 'document') . '.pdf')
                    ->withMime('application/pdf'),
            ];
        }

        return [];
    }
}

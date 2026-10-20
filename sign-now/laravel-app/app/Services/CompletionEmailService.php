<?php

namespace App\Services;

use App\Mail\DocumentCompleted;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Recipient;
use Illuminate\Support\Facades\Mail;

class CompletionEmailService
{
    public function __construct(
        private PdfStampingService $stampingService,
    ) {}

    /**
     * Queue the completion email (signed PDF attached) to every recipient
     * who has signed. Returns the number of emails queued.
     */
    public function sendToSignedRecipients(Document $doc, string $trigger = 'manual'): int
    {
        $recipients = Recipient::where('document_id', $doc->id)
            ->where('email', '!=', '__admin__')
            ->where('status', 'signed')
            ->get();

        if ($recipients->isEmpty()) {
            return 0;
        }

        // A failed render shouldn't block the notification — send without
        // the attachment and leave the error in the log.
        $pdfPath = null;
        try {
            $pdfPath = $this->stampingService->renderFromDb($doc);
        } catch (\Throwable $e) {
            \Log::error("CompletionEmailService: PDF render failed for {$doc->uuid}", ['error' => $e->getMessage()]);
        }
        if (!$pdfPath || !file_exists($pdfPath)) {
            $pdfPath = null;
        }

        foreach ($recipients as $recipient) {
            Mail::to($recipient->email)->queue(
                new DocumentCompleted($doc, $recipient->email, $pdfPath)
            );
        }

        AuditLog::log('nda_complete_sent', $doc, [
            'performed_by' => auth()->id() ? (string) auth()->id() : 'system',
            'performed_by_type' => $trigger === 'manual' ? 'admin' : 'system',
            'meta' => ['sent_count' => $recipients->count(), 'trigger' => $trigger],
        ]);

        return $recipients->count();
    }
}

<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Recipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DocumentCompletedWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public Document $document,
    ) {}

    public function handle(): void
    {
        $doc = $this->document->fresh();
        if (!$doc) {
            return;
        }

        $url = $doc->webhook_url
            ?: $doc->template?->webhook_url
            ?: config('services.webhook.default_url');

        if (!$url) {
            return;
        }

        $recipients = Recipient::where('document_id', $doc->id)
            ->where('email', '!=', '__admin__')
            ->get()
            ->map(fn (Recipient $r) => [
                'email' => $r->email,
                'name' => $r->name,
                'status' => $r->status,
                'signed_at' => $r->signed_at?->toIso8601String(),
            ]);

        $payload = [
            'event' => 'document.completed',
            'document_id' => $doc->uuid,
            'title' => $doc->title ?: $doc->original_filename,
            'template_id' => $doc->template?->uuid,
            'status' => $doc->status,
            'recipients' => $recipients,
            'view_url' => url("/documents/{$doc->uuid}/view"),
            'completed_at' => now()->toIso8601String(),
        ];

        $response = Http::timeout(15)
            ->withHeaders(['X-SignNow-Event' => 'document.completed'])
            ->post($url, $payload);

        if ($response->failed()) {
            AuditLog::log('webhook_failed', $doc, [
                'performed_by' => 'system',
                'performed_by_type' => 'system',
                'meta' => ['url' => $url, 'status' => $response->status(), 'attempt' => $this->attempts()],
            ]);

            // Throw so the queue retries (tries/backoff above).
            $response->throw();
        }

        AuditLog::log('webhook_sent', $doc, [
            'performed_by' => 'system',
            'performed_by_type' => 'system',
            'meta' => ['url' => $url, 'status' => $response->status()],
        ]);
    }
}

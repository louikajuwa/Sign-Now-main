<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class SocketNotificationService
{
    private const CHANNEL = 'docsign_events';

    public function notifyDashboard(string $event, array $data = []): void
    {
        Redis::publish(self::CHANNEL, json_encode([
            'event' => $event,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ]));
    }

    public function recipientSigned(string $docUuid, string $recipientEmail, string $docTitle): void
    {
        $this->notifyDashboard('recipient_signed', [
            'doc_uuid' => $docUuid,
            'recipient_email' => $recipientEmail,
            'doc_title' => $docTitle,
        ]);
    }

    public function documentFinalized(string $docUuid, string $docTitle): void
    {
        $this->notifyDashboard('document_finalized', [
            'doc_uuid' => $docUuid,
            'doc_title' => $docTitle,
        ]);
    }

    public function documentStatusChanged(string $docUuid, string $newStatus): void
    {
        $this->notifyDashboard('status_changed', [
            'doc_uuid' => $docUuid,
            'status' => $newStatus,
        ]);
    }
}

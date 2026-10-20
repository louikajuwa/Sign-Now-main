<?php

namespace App\Listeners;

use App\Events\DocumentFinalized;
use App\Events\DocumentStatusChanged;
use App\Events\RecipientSigned;
use App\Services\SocketNotificationService;

class NotifyDashboardViaSocket
{
    public function __construct(
        protected SocketNotificationService $socketService,
    ) {}

    public function handleRecipientSigned(RecipientSigned $event): void
    {
        $this->socketService->recipientSigned(
            $event->document->uuid,
            $event->recipient->email,
            $event->document->title,
        );
    }

    public function handleDocumentFinalized(DocumentFinalized $event): void
    {
        $this->socketService->documentFinalized(
            $event->document->uuid,
            $event->document->title,
        );
    }

    public function handleDocumentStatusChanged(DocumentStatusChanged $event): void
    {
        $this->socketService->documentStatusChanged(
            $event->document->uuid,
            $event->newStatus,
        );
    }
}

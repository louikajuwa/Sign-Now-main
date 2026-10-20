<?php

namespace App\Providers;

use App\Events\DocumentFinalized;
use App\Events\DocumentStatusChanged;
use App\Events\RecipientSigned;
use App\Listeners\NotifyDashboardViaSocket;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        RecipientSigned::class => [
            [NotifyDashboardViaSocket::class, 'handleRecipientSigned'],
        ],
        DocumentFinalized::class => [
            [NotifyDashboardViaSocket::class, 'handleDocumentFinalized'],
        ],
        DocumentStatusChanged::class => [
            [NotifyDashboardViaSocket::class, 'handleDocumentStatusChanged'],
        ],
    ];
}

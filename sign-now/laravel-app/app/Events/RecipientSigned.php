<?php

namespace App\Events;

use App\Models\Document;
use App\Models\Recipient;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecipientSigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Document $document,
        public Recipient $recipient,
    ) {}
}

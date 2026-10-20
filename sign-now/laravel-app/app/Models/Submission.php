<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Submission extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'user_id',
        'recipient_id',
        'output_path',
        'ip_address',
        'user_agent',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(SubmissionValue::class);
    }
}

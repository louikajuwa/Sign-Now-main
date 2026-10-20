<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'email',
        'name',
        'token',
        'status',
        'sent_at',
        'opened_at',
        'signed_at',
        'submission_id',
        'customer_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isExpired(): bool
    {
        // Measured from the latest of creation / last (re)send, so a rotated
        // token (resendAll) gets a fresh 30-day window.
        $since = $this->sent_at && $this->sent_at->gt($this->created_at) ? $this->sent_at : $this->created_at;

        return $since->diffInDays(now()) > 30;
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}

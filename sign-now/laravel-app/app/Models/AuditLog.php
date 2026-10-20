<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'action',
        'doc_uuid',
        'doc_id',
        'performed_by',
        'performed_by_type',
        'ip_address',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public static function log(string $action, ?Document $doc = null, array $extra = []): self
    {
        return self::create(array_merge([
            'action' => $action,
            'doc_uuid' => $doc?->uuid,
            'doc_id' => $doc?->id,
            'ip_address' => request()->ip(),
        ], $extra));
    }
}

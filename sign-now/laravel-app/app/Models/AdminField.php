<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminField extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'page',
        'type',
        'pos_x',
        'pos_y',
        'width',
        'height',
        'value',
        'created_by',
    ];

    protected $casts = [
        'pos_x' => 'float',
        'pos_y' => 'float',
        'width' => 'float',
        'height' => 'float',
        'created_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}

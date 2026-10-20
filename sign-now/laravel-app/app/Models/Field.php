<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Field extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'field_uid',
        'page',
        'type',
        'pos_x',
        'pos_y',
        'width',
        'height',
        'label',
        'name',
        'required',
        'font_size',
        'assigned_to',
        'customer_key',
        'customer_value',
        'default_value',
        'sort_order',
    ];

    protected $casts = [
        'required' => 'boolean',
        'pos_x' => 'float',
        'pos_y' => 'float',
        'width' => 'float',
        'height' => 'float',
        'font_size' => 'float',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function submissionValues(): HasMany
    {
        return $this->hasMany(SubmissionValue::class);
    }
}

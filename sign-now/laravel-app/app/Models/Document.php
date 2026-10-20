<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'original_filename',
        'title',
        'file_path',
        'page_count',
        'render_scale',
        'status',
        'is_template',
        'template_id',
        'is_api_template',
        'api_key',
        'webhook_url',
        'main_nda_api',
        'last_activity_at',
    ];

    protected $casts = [
        'is_template' => 'boolean',
        'is_api_template' => 'boolean',
        'main_nda_api' => 'boolean',
        'render_scale' => 'float',
        'last_activity_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Document $doc) {
            if (empty($doc->uuid)) {
                $doc->uuid = Str::uuid()->toString();
            }
        });
    }

    public function scopeAccessibleBy($query, ?User $user)
    {
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        return $user->isAdmin() ? $query : $query->where('user_id', $user->id);
    }

    public static function findAccessible(string $uuid): static
    {
        return static::accessibleBy(auth()->user())->where('uuid', $uuid)->firstOrFail();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'template_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class)->orderBy('sort_order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(Recipient::class);
    }

    public function adminFields(): HasMany
    {
        return $this->hasMany(AdminField::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'doc_id');
    }

    public function latestSubmission()
    {
        return $this->hasOne(Submission::class)->latestOfMany();
    }

    public function getStoragePathAttribute(): string
    {
        return "docsign/{$this->uuid}";
    }
}

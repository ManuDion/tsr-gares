<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdministrativeDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_type',
        'label',
        'original_name',
        'file_name',
        'mime_type',
        'size',
        'disk',
        'path',
        'expires_at',
        'is_active',
        'uploaded_by',
        'updated_by',
        'last_renewed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'is_active' => 'boolean',
            'last_renewed_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'subject',
        'content',
        'status',
        'control_date',
        'concerned_date',
        'gares',
        'operations',
        'payload',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'control_date' => 'date',
            'concerned_date' => 'date',
            'gares' => 'array',
            'operations' => 'array',
            'payload' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

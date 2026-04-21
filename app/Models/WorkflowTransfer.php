<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkflowTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'reference',
        'status',
        'origin_department_id',
        'destination_department_id',
        'transferred_by',
        'received_by',
        'transferred_at',
        'received_at',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'transferred_at' => 'datetime',
            'received_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function originDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'origin_department_id');
    }

    public function destinationDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'destination_department_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}

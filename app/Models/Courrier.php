<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Courrier extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'subject',
        'direction',
        'priority',
        'status',
        'origin_department_id',
        'destination_department_id',
        'gare_id',
        'received_at',
        'due_at',
        'description',
        'created_by',
        'updated_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'due_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function originDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'origin_department_id');
    }

    public function destinationDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'destination_department_id');
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function justificatives(): MorphMany
    {
        return $this->morphMany(PieceJustificative::class, 'attachable');
    }
}

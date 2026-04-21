<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    protected $fillable = [
        'employee_id',
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
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

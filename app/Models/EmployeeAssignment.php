<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAssignment extends Model
{
    protected $fillable = [
        'employee_id',
        'department_id',
        'gare_id',
        'job_title',
        'assigned_at',
        'ended_at',
        'decision_reference',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

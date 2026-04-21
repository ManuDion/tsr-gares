<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_code',
        'first_name',
        'last_name',
        'full_name',
        'phone',
        'email',
        'job_title',
        'hire_date',
        'employment_status',
        'user_id',
        'department_id',
        'gare_id',
        'mobile_app_enabled',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'mobile_app_enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeAssignment::class)->latest('assigned_at');
    }
}

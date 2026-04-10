<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyControl extends Model
{
    use HasFactory;

    protected $fillable = [
        'gare_id',
        'control_date',
        'concerned_date',
        'has_recette',
        'has_depense',
        'has_versement',
        'is_compliant',
        'missing_operations',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'control_date' => 'date',
            'concerned_date' => 'date',
            'has_recette' => 'boolean',
            'has_depense' => 'boolean',
            'has_versement' => 'boolean',
            'is_compliant' => 'boolean',
            'missing_operations' => 'array',
        ];
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}

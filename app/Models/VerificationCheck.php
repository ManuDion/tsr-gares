<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_scope',
        'gare_id',
        'operation_date',
        'recettes_total',
        'depenses_total',
        'versements_total',
        'expected_versement',
        'difference',
        'status',
        'modifications_enabled_until',
        'review_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'operation_date' => 'date',
            'recettes_total' => 'decimal:2',
            'depenses_total' => 'decimal:2',
            'versements_total' => 'decimal:2',
            'expected_versement' => 'decimal:2',
            'difference' => 'decimal:2',
            'modifications_enabled_until' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isBalanced(): bool
    {
        return (float) $this->difference === 0.0;
    }
}

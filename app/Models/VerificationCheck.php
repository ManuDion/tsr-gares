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
        'recettes_inter_total',
        'recettes_national_total',
        'depenses_total',
        'depenses_inter_total',
        'depenses_national_total',
        'versements_total',
        'versements_inter_total',
        'versements_national_total',
        'expected_versement',
        'expected_inter_versement',
        'expected_national_versement',
        'difference',
        'difference_inter',
        'difference_national',
        'status',
        'control_mode',
        'modifications_enabled_until',
        'review_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'operation_date' => 'date',
            'recettes_total' => 'integer',
            'recettes_inter_total' => 'integer',
            'recettes_national_total' => 'integer',
            'depenses_total' => 'integer',
            'depenses_inter_total' => 'integer',
            'depenses_national_total' => 'integer',
            'versements_total' => 'integer',
            'versements_inter_total' => 'integer',
            'versements_national_total' => 'integer',
            'expected_versement' => 'integer',
            'expected_inter_versement' => 'integer',
            'expected_national_versement' => 'integer',
            'difference' => 'integer',
            'difference_inter' => 'integer',
            'difference_national' => 'integer',
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
        return (int) $this->difference === 0;
    }
}

<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Recette extends Model
{
    use HasFactory;

    protected $fillable = [
        'gare_id',
        'operation_date',
        'amount',
        'ticket_inter_amount',
        'ticket_national_amount',
        'bagage_inter_amount',
        'bagage_national_amount',
        'reference',
        'description',
        'created_by',
        'updated_by',
        'force_unlocked_until',
        'unlock_reason',
        'unlocked_by',
    ];

    protected function casts(): array
    {
        return [
            'operation_date' => 'date',
            'amount' => 'decimal:2',
            'ticket_inter_amount' => 'decimal:2',
            'ticket_national_amount' => 'decimal:2',
            'bagage_inter_amount' => 'decimal:2',
            'bagage_national_amount' => 'decimal:2',
            'force_unlocked_until' => 'datetime',
        ];
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

    public function unlockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(RecetteHistory::class);
    }

    public function justificatives(): MorphMany
    {
        return $this->morphMany(PieceJustificative::class, 'attachable');
    }

    public function isEditableBy(User $user): bool
    {
        if ($user->isAdmin() || $user->isResponsable()) {
            return true;
        }

        if (! $user->hasAccessToGare($this->gare_id)) {
            return false;
        }

        return $this->created_at?->greaterThanOrEqualTo(now()->subHours(48))
            || ($this->force_unlocked_until instanceof CarbonInterface && $this->force_unlocked_until->isFuture());
    }

    public function recetteBreakdown(): array
    {
        return [
            'ticket_inter_amount' => (float) $this->ticket_inter_amount,
            'ticket_national_amount' => (float) $this->ticket_national_amount,
            'bagage_inter_amount' => (float) $this->bagage_inter_amount,
            'bagage_national_amount' => (float) $this->bagage_national_amount,
        ];
    }
}

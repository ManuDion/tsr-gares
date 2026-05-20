<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Recette extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_scope',
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
            'amount' => 'integer',
            'ticket_inter_amount' => 'integer',
            'ticket_national_amount' => 'integer',
            'bagage_inter_amount' => 'integer',
            'bagage_national_amount' => 'integer',
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
        if ($user->canAdministerFinancialScope($this->service_scope)) {
            return true;
        }

        $hasActiveUnlock = $this->force_unlocked_until instanceof CarbonInterface
            && $this->force_unlocked_until->isFuture();
        $hasDirectAccess = $user->hasAccessToGare($this->gare_id, $this->service_scope);

        if (! $hasDirectAccess) {
            return $hasActiveUnlock
                && $user->canEditUnlockedVirtualGareEntry((int) $this->gare_id, $this->service_scope);
        }

        if ($this->created_at?->greaterThanOrEqualTo(now()->subHours(48))) {
            return true;
        }

        if ($hasActiveUnlock) {
            return true;
        }

        return $this->hasActiveVerificationAdjustment();
    }

    protected function hasActiveVerificationAdjustment(): bool
    {
        if (! $this->gare_id || ! $this->operation_date) {
            return false;
        }

        return VerificationCheck::query()
            ->where('service_scope', (string) ($this->service_scope ?? 'gares'))
            ->where('gare_id', (int) $this->gare_id)
            ->whereDate('operation_date', $this->operation_date)
            ->whereNotNull('modifications_enabled_until')
            ->where('modifications_enabled_until', '>', now())
            ->exists();
    }

    public function recetteBreakdown(): array
    {
        return [
            'ticket_inter_amount' => (int) $this->ticket_inter_amount,
            'ticket_national_amount' => (int) $this->ticket_national_amount,
            'bagage_inter_amount' => (int) $this->bagage_inter_amount,
            'bagage_national_amount' => (int) $this->bagage_national_amount,
        ];
    }

    public function interAmount(): int
    {
        return (int) round((float) $this->ticket_inter_amount + (float) $this->bagage_inter_amount, 0);
    }

    public function nationalAmount(): int
    {
        return (int) round((float) $this->ticket_national_amount + (float) $this->bagage_national_amount, 0);
    }

    public function scopeFinanciallyValidated(Builder $query): Builder
    {
        return $query->where(function (Builder $inner) {
            $inner
                ->whereHas('gare', function (Builder $gareQuery) {
                    $gareQuery->where('is_virtual', true)
                        ->orWhere('versement_mode', '!=', 'cashier');
                })
                ->orWhereExists(function ($exists) {
                    $exists->selectRaw('1')
                        ->from('cashier_receipt_confirmations as crc')
                        ->join('gares as g', 'g.id', '=', 'crc.gare_id')
                        ->whereColumn('crc.service_scope', 'recettes.service_scope')
                        ->whereColumn('crc.gare_id', 'recettes.gare_id')
                        ->whereColumn('crc.operation_date', 'recettes.operation_date')
                        ->whereColumn('crc.cashier_id', 'g.cashier_user_id')
                        ->where('crc.is_verified', true);
                });
        });
    }
}

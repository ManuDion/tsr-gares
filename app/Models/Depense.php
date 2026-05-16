<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Depense extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_scope',
        'gare_id',
        'operation_date',
        'amount',
        'motif',
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

    public function justificatives(): MorphMany
    {
        return $this->morphMany(PieceJustificative::class, 'attachable');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(DepenseHistory::class);
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
}

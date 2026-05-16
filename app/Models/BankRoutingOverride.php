<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BankRoutingOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_scope',
        'forced_account_type',
        'start_date',
        'end_date',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function gares(): BelongsToMany
    {
        return $this->belongsToMany(
            Gare::class,
            'bank_routing_override_gare',
            'bank_routing_override_id',
            'gare_id'
        )->withTimestamps();
    }

    public function coversDate(CarbonInterface $date): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($date->lt($this->start_date)) {
            return false;
        }

        if ($this->end_date && $date->gt($this->end_date)) {
            return false;
        }

        return true;
    }

    public function appliesToGare(?int $gareId): bool
    {
        if (! $gareId) {
            return $this->gares()->count() === 0;
        }

        if ($this->relationLoaded('gares')) {
            return $this->gares->isEmpty() || $this->gares->contains('id', $gareId);
        }

        return ! $this->gares()->exists() || $this->gares()->whereKey($gareId)->exists();
    }
}

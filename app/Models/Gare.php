<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gare extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'city',
        'zone',
        'address',
        'versement_mode',
        'cashier_user_id',
        'activity_mode',
        'is_virtual',
        'virtual_owner_user_id',
        'virtual_scope',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_virtual' => 'boolean',
        ];
    }

    public function recettes(): HasMany
    {
        return $this->hasMany(Recette::class);
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class);
    }

    public function versementsBancaires(): HasMany
    {
        return $this->hasMany(VersementBancaire::class);
    }

    public function zoneManagers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps();
    }

    public function dailyControls(): HasMany
    {
        return $this->hasMany(DailyControl::class);
    }

    public function assignedCashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function virtualOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'virtual_owner_user_id');
    }

    public function cashierConfirmations(): HasMany
    {
        return $this->hasMany(CashierReceiptConfirmation::class);
    }

    public function isInterOnly(): bool
    {
        return ($this->activity_mode ?? 'mixed') === 'inter_only';
    }
}

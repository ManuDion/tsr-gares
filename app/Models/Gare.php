<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
}

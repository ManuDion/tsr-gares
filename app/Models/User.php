<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'gare_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
        ];
    }

    public function primaryGare(): BelongsTo
    {
        return $this->belongsTo(Gare::class, 'gare_id');
    }

    public function gares(): BelongsToMany
    {
        return $this->belongsToMany(Gare::class)->withTimestamps();
    }

    public function notificationHistories()
    {
        return $this->hasMany(NotificationHistory::class);
    }

    public function canViewAllGares(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Responsable], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isResponsable(): bool
    {
        return $this->role === UserRole::Responsable;
    }

    public function isChefDeGare(): bool
    {
        return $this->role === UserRole::ChefDeGare;
    }

    public function isCaissiere(): bool
    {
        return in_array($this->role, [UserRole::Caissiere, UserRole::ChefDeZone], true);
    }

    public function isChefDeZone(): bool
    {
        return $this->isCaissiere();
    }

    public function canCreateFinancialEntry(): bool
    {
        return $this->isChefDeGare() || $this->isCaissiere();
    }

    public function accessibleGareIds(): array
    {
        if ($this->canViewAllGares()) {
            return Gare::query()->pluck('id')->all();
        }

        if ($this->isChefDeGare()) {
            return array_values(array_filter([$this->gare_id]));
        }

        if ($this->isCaissiere()) {
            return $this->gares()->pluck('gares.id')->all();
        }

        return [];
    }

    public function hasAccessToGare(int $gareId): bool
    {
        if ($this->canViewAllGares()) {
            return true;
        }

        return in_array($gareId, $this->accessibleGareIds(), true);
    }

    public function roleLabel(): string
    {
        return $this->role?->label() ?? '—';
    }
}

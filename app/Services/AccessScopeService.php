<?php

namespace App\Services;

use App\Models\Gare;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class AccessScopeService
{
    public function scopeForUser(Builder $query, User $user, string $column = 'gare_id'): Builder
    {
        if ($user->canViewAllGares()) {
            return $query;
        }

        return $query->whereIn($column, $user->accessibleGareIds());
    }

    public function availableGares(User $user)
    {
        $query = Gare::query()->where('is_active', true)->orderBy('name');

        if ($user->canViewAllGares()) {
            return $query->get();
        }

        return $query->whereIn('id', $user->accessibleGareIds())->get();
    }

    public function resolveGareIdForCreation(User $user, ?int $requestedGareId): int
    {
        if ($user->isChefDeGare()) {
            if (! $user->gare_id) {
                throw ValidationException::withMessages([
                    'gare_id' => "Aucune gare n'est affectée à ce chef de gare.",
                ]);
            }

            return $user->gare_id;
        }

        if ($user->isCaissiere()) {
            if (! $requestedGareId || ! $user->hasAccessToGare($requestedGareId)) {
                throw ValidationException::withMessages([
                    'gare_id' => 'La gare sélectionnée est invalide pour votre profil.',
                ]);
            }

            return $requestedGareId;
        }

        throw ValidationException::withMessages([
            'role' => "Votre rôle n'autorise pas la saisie financière.",
        ]);
    }
}

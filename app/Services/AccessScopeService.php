<?php

namespace App\Services;

use App\Models\Gare;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class AccessScopeService
{
    public function scopeForUser(Builder $query, User $user, string $column = 'gare_id', ?string $serviceScope = null): Builder
    {
        if ($serviceScope && $this->hasServiceScopeColumn($query)) {
            $query->where($query->getModel()->getTable().'.service_scope', $serviceScope);
        }

        if ($user->canViewAllGares()) {
            return $query;
        }

        return $query->whereIn($column, $user->accessibleGareIds($serviceScope));
    }

    public function availableGares(User $user, ?string $serviceScope = null)
    {
        $query = Gare::query()->where('is_active', true)->orderBy('name');

        if ($user->canViewAllGares()) {
            return $query->get();
        }

        return $query->whereIn('id', $user->accessibleGareIds($serviceScope))->get();
    }

    public function resolveGareIdForCreation(User $user, ?int $requestedGareId, ?string $serviceScope = null): int
    {
        $serviceScope = $serviceScope ?: $user->defaultModule()->financialScope();

        if (($serviceScope === 'gares' && $user->isChefDeGare()) || ($serviceScope === 'courrier' && $user->isAgentCourrierGare())) {
            if (! $user->gare_id) {
                throw ValidationException::withMessages([
                    'gare_id' => "Aucune gare n'est affectée à cet utilisateur.",
                ]);
            }

            return $user->gare_id;
        }

        if (($serviceScope === 'gares' && $user->isCaissierGare()) || ($serviceScope === 'courrier' && $user->isCaissierCourrier())) {
            if (! $requestedGareId || ! $user->hasAccessToGare($requestedGareId, $serviceScope)) {
                throw ValidationException::withMessages([
                    'gare_id' => 'La gare sélectionnée est invalide pour votre profil.',
                ]);
            }

            return $requestedGareId;
        }

        throw ValidationException::withMessages([
            'role' => "Votre rôle n'autorise pas la saisie sur ce module.",
        ]);
    }

    protected function hasServiceScopeColumn(Builder $query): bool
    {
        return in_array('service_scope', $query->getModel()->getFillable(), true)
            || \Schema::hasColumn($query->getModel()->getTable(), 'service_scope');
    }
}

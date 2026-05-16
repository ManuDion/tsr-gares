<?php

namespace App\Services;

use App\Models\Gare;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class AccessScopeService
{
    public function __construct(protected CashierVirtualGareService $virtualGares)
    {
    }

    public function scopeForUser(Builder $query, User $user, string $column = 'gare_id', ?string $serviceScope = null): Builder
    {
        if ($serviceScope && $this->hasServiceScopeColumn($query)) {
            $query->where($query->getModel()->getTable().'.service_scope', $serviceScope);
        }

        if ($user->canViewAllGares($serviceScope)) {
            return $query;
        }

        return $query->whereIn($column, $user->accessibleGareIds($serviceScope));
    }

    public function availableGares(User $user, ?string $serviceScope = null)
    {
        $query = Gare::query()->where('is_active', true)->where('is_virtual', false)->orderBy('name');
        if ($serviceScope === 'courrier') {
            $query->where(function ($inner) {
                $inner->whereNull('activity_mode')
                    ->orWhere('activity_mode', '!=', 'inter_only');
            });
        }

        if ($user->canViewAllGares($serviceScope)) {
            return $query->get();
        }

        if ($serviceScope && $user->canActAsCashierForScope($serviceScope)) {
            $this->virtualGares->ensureForScope($user, $serviceScope);
        }

        return $query->whereIn('id', $user->accessibleGareIds($serviceScope))->get();
    }

    public function resolveGareIdForCreation(User $user, ?int $requestedGareId, ?string $serviceScope = null): int
    {
        $serviceScope = $serviceScope ?: $user->defaultModule()->financialScope();
        $resolvedGareId = null;

        if ($user->canActAsChefForScope((string) $serviceScope)) {
            if ($user->canUseMultiGareEntry()) {
                if (! $requestedGareId || ! $user->hasAccessToGare($requestedGareId, $serviceScope)) {
                    throw ValidationException::withMessages([
                        'gare_id' => 'La gare selectionnee est invalide pour votre profil.',
                    ]);
                }

                $resolvedGareId = (int) $requestedGareId;
            } else {
                if (! $user->gare_id) {
                    throw ValidationException::withMessages([
                        'gare_id' => "Aucune gare n'est affectee a cet utilisateur.",
                    ]);
                }

                $resolvedGareId = (int) $user->gare_id;
            }
        } elseif ($user->canActAsCashierForScope((string) $serviceScope)) {
            if (! $requestedGareId || ! $user->hasAccessToGare($requestedGareId, $serviceScope)) {
                throw ValidationException::withMessages([
                    'gare_id' => 'La gare selectionnee est invalide pour votre profil.',
                ]);
            }

            $resolvedGareId = (int) $requestedGareId;
        } else {
            throw ValidationException::withMessages([
                'role' => "Votre role n'autorise pas la saisie sur ce module.",
            ]);
        }

        if ($serviceScope === 'courrier') {
            $gare = Gare::query()->find($resolvedGareId);
            if ($gare?->isInterOnly()) {
                throw ValidationException::withMessages([
                    'gare_id' => 'Cette gare est en mode inter uniquement. Elle ne peut pas etre rattachee au service courrier.',
                ]);
            }
        }

        return $resolvedGareId;
    }

    protected function hasServiceScopeColumn(Builder $query): bool
    {
        return in_array('service_scope', $query->getModel()->getFillable(), true)
            || \Schema::hasColumn($query->getModel()->getTable(), 'service_scope');
    }
}

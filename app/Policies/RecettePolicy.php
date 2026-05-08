<?php

namespace App\Policies;

use App\Models\Recette;
use App\Models\User;

class RecettePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasGlobalVisibility() || $user->canAccessFinancialScope('gares') || $user->canAccessFinancialScope('courrier');
    }

    public function view(User $user, Recette $recette): bool
    {
        return $user->hasAccessToGare($recette->gare_id, $recette->service_scope);
    }

    public function create(User $user): bool
    {
        return $user->canAccessFinancialScope('gares') && $user->canActAsChefForScope('gares')
            || $user->canAccessFinancialScope('courrier') && $user->canActAsChefForScope('courrier');
    }

    public function update(User $user, Recette $recette): bool
    {
        return $recette->isEditableBy($user);
    }
}

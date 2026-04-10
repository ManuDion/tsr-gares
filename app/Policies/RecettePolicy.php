<?php

namespace App\Policies;

use App\Models\Recette;
use App\Models\User;

class RecettePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Recette $recette): bool
    {
        return $user->hasAccessToGare($recette->gare_id);
    }

    public function create(User $user): bool
    {
        return $user->canCreateFinancialEntry();
    }

    public function update(User $user, Recette $recette): bool
    {
        return $recette->isEditableBy($user);
    }
}

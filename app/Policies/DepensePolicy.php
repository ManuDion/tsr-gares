<?php

namespace App\Policies;

use App\Models\Depense;
use App\Models\User;

class DepensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasGlobalVisibility() || $user->canAccessFinancialScope('gares') || $user->canAccessFinancialScope('courrier');
    }

    public function view(User $user, Depense $depense): bool
    {
        return $user->hasAccessToGare($depense->gare_id, $depense->service_scope);
    }

    public function create(User $user): bool
    {
        return $user->canCreateFinancialEntry('gares') || $user->canCreateFinancialEntry('courrier');
    }

    public function update(User $user, Depense $depense): bool
    {
        return $depense->isEditableBy($user);
    }
}

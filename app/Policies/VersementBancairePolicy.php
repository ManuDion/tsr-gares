<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VersementBancaire;

class VersementBancairePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasGlobalVisibility() || $user->canAccessGaresModule() || $user->canAccessCourrierModule();
    }

    public function view(User $user, VersementBancaire $versement): bool
    {
        return $user->hasAccessToGare($versement->gare_id, $versement->service_scope);
    }

    public function create(User $user): bool
    {
        return $user->canCreateFinancialEntry();
    }

    public function update(User $user, VersementBancaire $versement): bool
    {
        return $versement->isEditableBy($user);
    }
}

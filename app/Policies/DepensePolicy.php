<?php

namespace App\Policies;

use App\Models\Depense;
use App\Models\User;

class DepensePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Depense $depense): bool
    {
        return $user->hasAccessToGare($depense->gare_id);
    }

    public function create(User $user): bool
    {
        return $user->canCreateFinancialEntry();
    }
}

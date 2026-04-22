<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VerificationCheck;

class VerificationCheckPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canSuperviseFinancialScope('gares') || $user->canSuperviseFinancialScope('courrier');
    }

    public function update(User $user, VerificationCheck $check): bool
    {
        if (! $user->canSuperviseFinancialScope($check->service_scope)) {
            return false;
        }

        return $user->canViewAllGares() || $user->hasAccessToGare($check->gare_id, $check->service_scope);
    }
}

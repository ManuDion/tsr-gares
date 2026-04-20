<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VerificationCheck;

class VerificationCheckPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isResponsable();
    }

    public function update(User $user, VerificationCheck $check): bool
    {
        return $user->isAdmin() || $user->isResponsable();
    }
}

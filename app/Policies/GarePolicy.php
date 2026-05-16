<?php

namespace App\Policies;

use App\Enums\ServiceModule;
use App\Models\Gare;
use App\Models\User;

class GarePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasGlobalVisibility() || $user->canAccessGaresModule();
    }

    public function view(User $user, Gare $gare): bool
    {
        return $user->hasAccessToGare($gare->id, 'gares');
    }

    public function create(User $user): bool
    {
        return $user->canAdministerModule(ServiceModule::Gares);
    }

    public function update(User $user, Gare $gare): bool
    {
        return $user->canAdministerModule(ServiceModule::Gares);
    }

    public function delete(User $user, Gare $gare): bool
    {
        return $user->canAdministerModule(ServiceModule::Gares);
    }
}

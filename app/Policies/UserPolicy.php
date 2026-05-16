<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasGlobalVisibility() || $user->isServiceAdmin();
    }

    public function create(User $user): bool
    {
        return $user->hasGlobalVisibility() || $user->isServiceAdmin();
    }

    public function update(User $user, User $model): bool
    {
        if ($user->hasGlobalVisibility()) {
            return true;
        }

        $module = $user->assignedModule();
        if (! $module || ! $user->isServiceAdminForModule($module)) {
            return false;
        }

        if ($model->hasGlobalVisibility()) {
            return false;
        }

        return $model->belongsToModule($module);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->update($user, $model);
    }
}

<?php

namespace App\Policies;

use App\Models\AdministrativeDocument;
use App\Models\User;

class AdministrativeDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isResponsable() || $user->isControleur();
    }

    public function view(User $user, AdministrativeDocument $document): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isControleur();
    }

    public function update(User $user, AdministrativeDocument $document): bool
    {
        return $user->isControleur();
    }

    public function delete(User $user, AdministrativeDocument $document): bool
    {
        return $user->isAdmin() || $user->isControleur();
    }
}

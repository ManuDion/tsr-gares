<?php

namespace App\Policies;

use App\Models\NotificationHistory;
use App\Models\User;

class NotificationHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, NotificationHistory $notificationHistory): bool
    {
        return $user->canViewAllGares() || $notificationHistory->user_id === $user->id;
    }
}

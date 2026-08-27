<?php

namespace App\Policies;

use App\Models\BusinessTrip;
use App\Models\User;

class BusinessTripPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BusinessTrip $businessTrip): bool
    {
        return $user->isSuperAdmin()
            || $user->isAdmin()
            || $businessTrip->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->status === 'active';
    }

    public function cancel(User $user, BusinessTrip $businessTrip): bool
    {
        return $businessTrip->user_id === $user->id && $businessTrip->status === 'pending';
    }

    public function approve(User $user, BusinessTrip $businessTrip): bool
    {
        return ($user->isSuperAdmin() || $user->isAdmin())
            && $businessTrip->status === 'pending';
    }

    public function reject(User $user, BusinessTrip $businessTrip): bool
    {
        return ($user->isSuperAdmin() || $user->isAdmin())
            && $businessTrip->status === 'pending';
    }
}

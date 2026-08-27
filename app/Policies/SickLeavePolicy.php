<?php

namespace App\Policies;

use App\Models\SickLeave;
use App\Models\User;

class SickLeavePolicy
{
    /**
     * Determine whether the user can view the sick leave listing.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the sick leave.
     */
    public function view(User $user, SickLeave $sickLeave): bool
    {
        return $user->isSuperAdmin()
            || $user->isAdmin()
            || $sickLeave->user_id === $user->id;
    }

    /**
     * Determine whether the user can create sick leaves.
     */
    public function create(User $user): bool
    {
        return $user->status === 'active';
    }

    /**
     * Determine whether the user can cancel the sick leave.
     */
    public function cancel(User $user, SickLeave $sickLeave): bool
    {
        return $sickLeave->user_id === $user->id && $sickLeave->status === 'pending';
    }

    /**
     * Determine whether the user can approve the sick leave.
     */
    public function approve(User $user, SickLeave $sickLeave): bool
    {
        return ($user->isSuperAdmin() || $user->isAdmin())
            && $sickLeave->status === 'pending';
    }

    /**
     * Determine whether the user can reject the sick leave.
     */
    public function reject(User $user, SickLeave $sickLeave): bool
    {
        return ($user->isSuperAdmin() || $user->isAdmin())
            && $sickLeave->status === 'pending';
    }
}

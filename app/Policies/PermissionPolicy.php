<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;

class PermissionPolicy
{
    /**
     * Determine whether the user can view the permission listing.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the permission.
     */
    public function view(User $user, Permission $permission): bool
    {
        return $user->isSuperAdmin()
            || $user->isAdmin()
            || $permission->user_id === $user->id;
    }

    /**
     * Determine whether the user can create permissions.
     */
    public function create(User $user): bool
    {
        return $user->status === 'active';
    }

    /**
     * Determine whether the user can cancel the permission.
     */
    public function cancel(User $user, Permission $permission): bool
    {
        return $permission->user_id === $user->id && $permission->status === 'pending';
    }

    /**
     * Determine whether the user can approve the permission.
     */
    public function approve(User $user, Permission $permission): bool
    {
        return ($user->isSuperAdmin() || $user->isAdmin())
            && $permission->status === 'pending';
    }

    /**
     * Determine whether the user can reject the permission.
     */
    public function reject(User $user, Permission $permission): bool
    {
        return ($user->isSuperAdmin() || $user->isAdmin())
            && $permission->status === 'pending';
    }
}

<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    /**
     * Determine whether the user can view the leave listing.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the leave request.
     */
    public function view(User $user, LeaveRequest $leave): bool
    {
        return $user->isSuperAdmin()
            || $user->isAdmin()
            || $leave->user_id === $user->id;
    }

    /**
     * Determine whether the user can create leave requests.
     */
    public function create(User $user): bool
    {
        return $user->status === 'active';
    }

    /**
     * Determine whether the user can cancel the leave request.
     */
    public function cancel(User $user, LeaveRequest $leave): bool
    {
        return $leave->user_id === $user->id && $leave->status === 'pending';
    }

    /**
     * Determine whether the user can approve the leave request.
     */
    public function approve(User $user, LeaveRequest $leave): bool
    {
        return ($user->isSuperAdmin() || $user->isAdmin())
            && $leave->status === 'pending';
    }

    /**
     * Determine whether the user can reject the leave request.
     */
    public function reject(User $user, LeaveRequest $leave): bool
    {
        return ($user->isSuperAdmin() || $user->isAdmin())
            && $leave->status === 'pending';
    }
}

<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    /**
     * Determine whether the user can view any attendance records.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    /**
     * Determine whether the user can access the attendance recap.
     */
    public function recap(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    /**
     * Determine whether the user can export the attendance recap.
     */
    public function export(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    /**
     * Determine whether the user can view a single attendance record.
     */
    public function view(User $user, Attendance $attendance): bool
    {
        return $user->isSuperAdmin()
            || $user->isAdmin()
            || $attendance->user_id === $user->id;
    }

    /**
     * Determine whether the user can check in (create an attendance record).
     */
    public function create(User $user): bool
    {
        return $user->status === 'active';
    }

    /**
     * Determine whether the user can update an attendance record
     * (used for check out and future corrections).
     */
    public function update(User $user, Attendance $attendance): bool
    {
        return $user->isSuperAdmin()
            || $user->isAdmin()
            || $attendance->user_id === $user->id;
    }
}

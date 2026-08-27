<?php

namespace App\Policies;

use App\Models\User;

class AttendanceSummaryPolicy
{
    /**
     * The attendance summary is an HR tool reserved for office admins.
     * Super admins and regular employees are explicitly denied.
     */
    public function view(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Excel export of the attendance summary is reserved for office
     * admins as well. Super admins and regular employees are denied.
     */
    public function export(User $user): bool
    {
        return $user->isAdmin();
    }
}

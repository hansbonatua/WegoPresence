<?php

namespace App\Policies;

use App\Models\User;

class AttendanceSummaryPolicy
{
    /**
     * The attendance summary is an HR tool available to super admins and
     * office admins. Regular employees are explicitly denied.
     */
    public function view(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    /**
     * Excel export of the attendance summary is available to super admins
     * and office admins. Regular employees are denied.
     */
    public function export(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin();
    }
}

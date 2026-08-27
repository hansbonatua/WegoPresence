<?php

namespace App\Policies;

use App\Models\AttendanceComplaint;
use App\Models\User;

class AttendanceComplaintPolicy
{
    /**
     * Determine whether the user can view the complaint listing.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the complaint.
     */
    public function view(User $user, AttendanceComplaint $complaint): bool
    {
        return $user->isSuperAdmin()
            || $this->managesComplaint($user, $complaint)
            || $complaint->user_id === $user->id;
    }

    /**
     * Determine whether the user can create complaints.
     */
    public function create(User $user): bool
    {
        return $user->status === 'active';
    }

    /**
     * Determine whether the user can cancel the complaint.
     */
    public function cancel(User $user, AttendanceComplaint $complaint): bool
    {
        return $complaint->user_id === $user->id && $complaint->status === 'pending';
    }

    /**
     * Determine whether the user can approve the complaint.
     */
    public function approve(User $user, AttendanceComplaint $complaint): bool
    {
        return ($user->isSuperAdmin() || $this->managesComplaint($user, $complaint))
            && $complaint->status === 'pending';
    }

    /**
     * Determine whether the user can reject the complaint.
     */
    public function reject(User $user, AttendanceComplaint $complaint): bool
    {
        return ($user->isSuperAdmin() || $this->managesComplaint($user, $complaint))
            && $complaint->status === 'pending';
    }

    /**
     * Admins only manage complaints raised by employees of their own office.
     */
    private function managesComplaint(User $user, AttendanceComplaint $complaint): bool
    {
        return $user->isAdmin()
            && $complaint->user?->office_id === $user->office_id;
    }
}

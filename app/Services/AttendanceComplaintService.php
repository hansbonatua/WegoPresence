<?php

namespace App\Services;

use App\Exceptions\AttendanceComplaintException;
use App\Models\Attendance;
use App\Models\AttendanceComplaint;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AttendanceComplaintService
{
    /**
     * Create a complaint for one of the user's own attendance records.
     * The owner and the status are always determined by the server.
     *
     * @param  array{attendance_id: int|string, complaint_reason: string}  $data
     *
     * @throws AttendanceComplaintException When the account is inactive,
     *                                      the attendance does not belong
     *                                      to the user, or the user already
     *                                      has a pending complaint for the
     *                                      same attendance record.
     */
    public function create(User $user, array $data): AttendanceComplaint
    {
        if ($user->status !== 'active') {
            throw new AttendanceComplaintException('Your account is not active.');
        }

        $attendance = Attendance::query()->find((int) $data['attendance_id']);

        if ($attendance === null || $attendance->user_id !== $user->id) {
            throw new AttendanceComplaintException('You can only complain about your own attendance records.');
        }

        $duplicateExists = AttendanceComplaint::query()
            ->where('attendance_id', $attendance->id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($duplicateExists) {
            throw new AttendanceComplaintException('You already submitted a complaint for this attendance record.');
        }

        return AttendanceComplaint::query()->create([
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'complaint_reason' => $data['complaint_reason'],
            'status' => 'pending',
        ]);
    }

    /**
     * Cancel a pending complaint by removing it.
     *
     * @throws AttendanceComplaintException When the complaint is not pending.
     */
    public function cancel(User $user, AttendanceComplaint $complaint): void
    {
        $this->assertPending($complaint);

        $complaint->delete();
    }

    /**
     * Approve a pending complaint.
     *
     * @throws AttendanceComplaintException When the complaint is not pending.
     */
    public function approve(User $user, AttendanceComplaint $complaint, ?string $notes = null): AttendanceComplaint
    {
        $this->assertPending($complaint);

        $complaint->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approval_notes' => $notes,
        ]);

        return $complaint->refresh();
    }

    /**
     * Reject a pending complaint.
     *
     * @throws AttendanceComplaintException When the complaint is not pending.
     */
    public function reject(User $user, AttendanceComplaint $complaint, ?string $notes = null): AttendanceComplaint
    {
        $this->assertPending($complaint);

        $complaint->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'approval_notes' => $notes,
        ]);

        return $complaint->refresh();
    }

    /**
     * Paginate complaints. Employees only see their own complaints,
     * admins see complaints from their own office, and super admins
     * see everything.
     *
     * @param  array{search?: string|null, status?: string|null}  $filters
     */
    public function paginate(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = AttendanceComplaint::query()
            ->with([
                'user:id,nip,name,office_id',
                'user.office:id,office_code,office_name,city',
                'attendance:id,user_id,attendance_date,check_in_time,check_out_time,attendance_status',
                'approver:id,name',
            ])
            ->latest();

        if ($user->isAdmin()) {
            $query->whereHas('user', function ($query) use ($user) {
                $query->where('office_id', $user->office_id);
            });
        } elseif (! $user->isSuperAdmin()) {
            $query->where('user_id', $user->id);
        }

        $search = $filters['search'] ?? null;

        if (filled($search)) {
            $query->where(function ($query) use ($search) {
                $query->whereRaw('lower(complaint_reason) like ?', ['%'.mb_strtolower($search).'%'])
                    ->orWhereHas('user', function ($query) use ($search) {
                        $query->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%'])
                            ->orWhereRaw('lower(nip) like ?', ['%'.mb_strtolower($search).'%']);
                    });
            });
        }

        $status = $filters['status'] ?? null;

        if (filled($status) && $this->isValidStatus($status)) {
            $query->where('status', $status);
        }

        return $query->paginate(10)->withQueryString();
    }

    /**
     * The attendance records the user may attach a complaint to.
     *
     * @return Collection<int, array{id: int, attendance_date: string, check_in_time: string|null, check_out_time: string|null, attendance_status: string|null}>
     */
    public function attendancesFor(User $user): Collection
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->latest('attendance_date')
            ->limit(50)
            ->get()
            ->map(fn (Attendance $attendance): array => [
                'id' => $attendance->id,
                'attendance_date' => $attendance->attendance_date->format('Y-m-d'),
                'check_in_time' => $attendance->check_in_time?->format('H:i'),
                'check_out_time' => $attendance->check_out_time?->format('H:i'),
                'attendance_status' => $attendance->attendance_status,
            ]);
    }

    /**
     * @throws AttendanceComplaintException
     */
    private function assertPending(AttendanceComplaint $complaint): void
    {
        if ($complaint->status !== 'pending') {
            throw new AttendanceComplaintException('Only pending complaints can be modified.');
        }
    }

    private function isValidStatus(string $status): bool
    {
        return in_array($status, ['pending', 'approved', 'rejected'], true);
    }
}

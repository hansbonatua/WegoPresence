<?php

namespace App\Services;

use App\Exceptions\LeaveException;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class LeaveService
{
    /**
     * Create a leave request for a user. The owner and the status are
     * always determined by the server.
     *
     * @param  array{start_date: string, end_date: string, reason: string}  $data
     */
    public function create(User $user, array $data): LeaveRequest
    {
        if ($user->status !== 'active') {
            throw new LeaveException('Your account is not active.');
        }

        if ($this->datesAreInvalid($data['start_date'], $data['end_date'])) {
            throw new LeaveException('The end date must be after or equal to the start date.');
        }

        return LeaveRequest::query()->create([
            'user_id' => $user->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);
    }

    /**
     * Cancel a pending leave request.
     *
     * @throws LeaveException When the leave request is not pending.
     */
    public function cancel(User $user, LeaveRequest $leave): LeaveRequest
    {
        $this->assertPending($leave);

        $leave->update(['status' => 'cancelled']);

        return $leave->refresh();
    }

    /**
     * Approve a pending leave request.
     *
     * @throws LeaveException When the leave request is not pending.
     */
    public function approve(User $user, LeaveRequest $leave, ?string $notes = null): LeaveRequest
    {
        $this->assertPending($leave);

        $leave->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approval_notes' => $notes,
        ]);

        return $leave->refresh();
    }

    /**
     * Reject a pending leave request.
     *
     * @throws LeaveException When the leave request is not pending.
     */
    public function reject(User $user, LeaveRequest $leave, ?string $notes = null): LeaveRequest
    {
        $this->assertPending($leave);

        $leave->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'approval_notes' => $notes,
        ]);

        return $leave->refresh();
    }

    /**
     * Paginate leave requests. Employees only see their own requests;
     * admins and super admins see every request.
     *
     * @param  array{search?: string|null, status?: string|null}  $filters
     */
    public function paginate(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = LeaveRequest::query()
            ->with(['user:id,name,nip', 'approver:id,name'])
            ->latest();

        if (! $user->isSuperAdmin() && ! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        $search = $filters['search'] ?? null;

        if (filled($search)) {
            $query->where(function ($query) use ($search) {
                $query->where('reason', 'ilike', "%{$search}%")
                    ->orWhereHas('user', function ($query) use ($search) {
                        $query->where('name', 'ilike', "%{$search}%")
                            ->orWhere('nip', 'ilike', "%{$search}%");
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
     * @throws LeaveException
     */
    private function assertPending(LeaveRequest $leave): void
    {
        if ($leave->status !== 'pending') {
            throw new LeaveException('Only pending leave requests can be modified.');
        }
    }

    private function datesAreInvalid(string $startDate, string $endDate): bool
    {
        return $endDate < $startDate;
    }

    private function isValidStatus(string $status): bool
    {
        return in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true);
    }
}

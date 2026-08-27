<?php

namespace App\Services;

use App\Exceptions\SickLeaveException;
use App\Models\SickLeave;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class SickLeaveService
{
    /**
     * Create a sick leave for a user. The owner and the status are
     * always determined by the server.
     *
     * @param  array{start_date: string, end_date: string, reason: string}  $data
     */
    public function create(User $user, array $data): SickLeave
    {
        if ($user->status !== 'active') {
            throw new SickLeaveException('Your account is not active.');
        }

        if ($this->datesAreInvalid($data['start_date'], $data['end_date'])) {
            throw new SickLeaveException('The end date must be after or equal to the start date.');
        }

        return SickLeave::query()->create([
            'user_id' => $user->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);
    }

    /**
     * Cancel a pending sick leave.
     *
     * @throws SickLeaveException When the sick leave is not pending.
     */
    public function cancel(User $user, SickLeave $sickLeave): SickLeave
    {
        $this->assertPending($sickLeave);

        $sickLeave->update(['status' => 'cancelled']);

        return $sickLeave->refresh();
    }

    /**
     * Approve a pending sick leave.
     *
     * @throws SickLeaveException When the sick leave is not pending.
     */
    public function approve(User $user, SickLeave $sickLeave, ?string $notes = null): SickLeave
    {
        $this->assertPending($sickLeave);

        $sickLeave->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approval_notes' => $notes,
        ]);

        return $sickLeave->refresh();
    }

    /**
     * Reject a pending sick leave.
     *
     * @throws SickLeaveException When the sick leave is not pending.
     */
    public function reject(User $user, SickLeave $sickLeave, ?string $notes = null): SickLeave
    {
        $this->assertPending($sickLeave);

        $sickLeave->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'approval_notes' => $notes,
        ]);

        return $sickLeave->refresh();
    }

    /**
     * Paginate sick leaves. Employees only see their own requests;
     * admins and super admins see every request.
     *
     * @param  array{search?: string|null, status?: string|null}  $filters
     */
    public function paginate(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = SickLeave::query()
            ->with(['user:id,name,nip', 'approver:id,name'])
            ->latest();

        if (! $user->isSuperAdmin() && ! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        $search = $filters['search'] ?? null;

        if (filled($search)) {
            $query->where(function ($query) use ($search) {
                $query->whereRaw('lower(reason) like ?', ['%'.mb_strtolower($search).'%'])
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
     * @throws SickLeaveException
     */
    private function assertPending(SickLeave $sickLeave): void
    {
        if ($sickLeave->status !== 'pending') {
            throw new SickLeaveException('Only pending sick leaves can be modified.');
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

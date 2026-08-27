<?php

namespace App\Services;

use App\Exceptions\PermissionException;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class PermissionService
{
    /**
     * Create a permission request for a user. The owner and the status
     * are always determined by the server.
     *
     * @param  array{type: string, start_date: string, end_date: string, reason: string}  $data
     */
    public function create(User $user, array $data): Permission
    {
        if ($user->status !== 'active') {
            throw new PermissionException('Your account is not active.');
        }

        if ($this->datesAreInvalid($data['start_date'], $data['end_date'])) {
            throw new PermissionException('The end date must be after or equal to the start date.');
        }

        return Permission::query()->create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);
    }

    /**
     * Cancel a pending permission request.
     *
     * @throws PermissionException When the permission is not pending.
     */
    public function cancel(User $user, Permission $permission): Permission
    {
        $this->assertPending($permission);

        $permission->update(['status' => 'cancelled']);

        return $permission->refresh();
    }

    /**
     * Approve a pending permission request.
     *
     * @throws PermissionException When the permission is not pending.
     */
    public function approve(User $user, Permission $permission, ?string $notes = null): Permission
    {
        $this->assertPending($permission);

        $permission->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approval_notes' => $notes,
        ]);

        return $permission->refresh();
    }

    /**
     * Reject a pending permission request.
     *
     * @throws PermissionException When the permission is not pending.
     */
    public function reject(User $user, Permission $permission, ?string $notes = null): Permission
    {
        $this->assertPending($permission);

        $permission->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'approval_notes' => $notes,
        ]);

        return $permission->refresh();
    }

    /**
     * Paginate permission requests. Employees only see their own
     * requests; admins and super admins see every request.
     *
     * @param  array{search?: string|null, status?: string|null}  $filters
     */
    public function paginate(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Permission::query()
            ->with(['user:id,name', 'approver:id,name'])
            ->latest();

        if (! $user->isSuperAdmin() && ! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        $search = $filters['search'] ?? null;

        if (filled($search)) {
            $query->where(function ($query) use ($search) {
                $query->where('reason', 'ilike', "%{$search}%")
                    ->orWhereHas('user', function ($query) use ($search) {
                        $query->where('name', 'ilike', "%{$search}%");
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
     * @throws PermissionException
     */
    private function assertPending(Permission $permission): void
    {
        if ($permission->status !== 'pending') {
            throw new PermissionException('Only pending permissions can be modified.');
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

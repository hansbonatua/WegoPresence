<?php

namespace App\Services;

use App\Exceptions\BusinessTripException;
use App\Models\BusinessTrip;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class BusinessTripService
{
    /** @param  array{start_date: string, end_date: string, destination: string, purpose: string, notes?: string|null, attachment?: string|null}  $data */
    public function create(User $user, array $data): BusinessTrip
    {
        if ($user->status !== 'active') {
            throw new BusinessTripException('Your account is not active.');
        }

        if ($this->datesAreInvalid($data['start_date'], $data['end_date'])) {
            throw new BusinessTripException('The end date must be after or equal to the start date.');
        }

        if ($this->hasOverlappingTrip($user->id, $data['start_date'], $data['end_date'])) {
            throw new BusinessTripException('You have an overlapping business trip within the selected date range.');
        }

        return BusinessTrip::query()->create([
            'user_id' => $user->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'destination' => $data['destination'],
            'purpose' => $data['purpose'],
            'notes' => $data['notes'] ?? null,
            'attachment' => $data['attachment'] ?? null,
            'status' => 'pending',
        ]);
    }

    public function cancel(User $user, BusinessTrip $businessTrip): BusinessTrip
    {
        $this->assertPending($businessTrip);
        $businessTrip->update(['status' => 'cancelled']);

        return $businessTrip->refresh();
    }

    public function approve(User $user, BusinessTrip $businessTrip, ?string $notes = null): BusinessTrip
    {
        $this->assertPending($businessTrip);
        $businessTrip->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approval_notes' => $notes,
        ]);

        return $businessTrip->refresh();
    }

    public function reject(User $user, BusinessTrip $businessTrip, ?string $notes = null): BusinessTrip
    {
        $this->assertPending($businessTrip);
        $businessTrip->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'approval_notes' => $notes,
        ]);

        return $businessTrip->refresh();
    }

    /** @param  array{search?: string|null, status?: string|null}  $filters */
    public function paginate(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = BusinessTrip::query()
            ->with(['user:id,name,nip', 'approver:id,name'])
            ->latest();

        if ($user->isSuperAdmin()) {
            // Super admins can see every business trip request.
        } elseif ($user->isAdmin()) {
            $query->whereHas('user', function ($query) use ($user) {
                $query->where('office_id', $user->office_id);
            });
        } else {
            $query->where('user_id', $user->id);
        }

        $search = $filters['search'] ?? null;
        if (filled($search)) {
            $query->where(function ($query) use ($search) {
                $query->where('destination', 'ilike', "%{$search}%")
                    ->orWhere('purpose', 'ilike', "%{$search}%")
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

    private function assertPending(BusinessTrip $businessTrip): void
    {
        if ($businessTrip->status !== 'pending') {
            throw new BusinessTripException('Only pending business trips can be modified.');
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

    /**
     * Check if the user has an overlapping active business trip.
     * Overlap: existing trip with status pending or approved whose date
     * range overlaps with the new range.
     */
    private function hasOverlappingTrip(int $userId, string $startDate, string $endDate): bool
    {
        return BusinessTrip::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($query) use ($startDate, $endDate) {
                        $query->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->exists();
    }
}

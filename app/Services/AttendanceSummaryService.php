<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\BusinessTrip;
use App\Models\LeaveRequest;
use App\Models\Permission;
use App\Models\SickLeave;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AttendanceSummaryService
{
    private const PRESENT = 'H';

    private const ABSENT = 'A';

    private const PERMISSION = 'I';

    private const LEAVE = 'C';

    private const SICK = 'S';

    private const DINAS = 'D';

    /**
     * Build the HR attendance matrix for the admin's own office.
     *
     * All data (users, attendances, approved permissions, approved leaves,
     * approved sick leaves) is loaded in a handful of batched queries and
     * combined in memory, so the number of queries does not grow with the
     * number of users or dates.
     *
     * @return array{users: array<int, array{nip: string, name: string, position: string, dates: array<string, string>}>, dates: array<int, string>, summary: array{total_users: int, hadir: int, absen: int, izin: int, cuti: int, sakit: int, dinas: int}}
     */
    public function getSummary(User $admin, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $dates = $this->dateRange($startDate, $endDate);

        $users = User::query()
            ->where('office_id', $admin->office_id)
            ->where('status', 'active')
            ->where('id', '!=', $admin->id)
            ->orderBy('nip')
            ->get(['id', 'nip', 'name', 'position']);

        $userIds = $users->pluck('id');

        $attendances = $this->attendancesByUser($userIds, $startDate, $endDate);
        $businessTrips = $this->approvedRangesByUser(BusinessTrip::class, $userIds, $startDate, $endDate);
        $sickLeaves = $this->approvedRangesByUser(SickLeave::class, $userIds, $startDate, $endDate);
        $leaves = $this->approvedRangesByUser(LeaveRequest::class, $userIds, $startDate, $endDate);
        $permissions = $this->approvedRangesByUser(Permission::class, $userIds, $startDate, $endDate);

        $rows = [];

        foreach ($users as $user) {
            $userDates = [];

            foreach ($dates as $date) {
                $userDates[$date] = $this->statusFor(
                    $date,
                    $user->id,
                    $attendances,
                    $businessTrips,
                    $sickLeaves,
                    $leaves,
                    $permissions,
                );
            }

            $rows[] = [
                'nip' => $user->nip,
                'name' => $user->name,
                'position' => $user->position,
                'dates' => $userDates,
            ];
        }

        return [
            'users' => $rows,
            'dates' => $dates,
            'summary' => $this->summarize($rows),
        ];
    }

    /**
     * Attendances grouped by user, as a set of date strings per user.
     *
     * @param  Collection<int, int>  $userIds
     * @return Collection<int, array<int, string>>
     */
    private function attendancesByUser($userIds, CarbonImmutable $startDate, CarbonImmutable $endDate): Collection
    {
        return Attendance::query()
            ->whereIn('user_id', $userIds)
            ->whereDate('attendance_date', '>=', $startDate->toDateString())
            ->whereDate('attendance_date', '<=', $endDate->toDateString())
            ->get(['user_id', 'attendance_date'])
            ->groupBy('user_id')
            ->map(fn ($items) => $items
                ->map(fn (Attendance $attendance): string => $attendance->attendance_date->format('Y-m-d'))
                ->all());
    }

    /**
     * Approved leaves/permissions/sick leaves grouped by user, keeping
     * each record's start and end date so a range can cover several days.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $model
     * @param  Collection<int, int>  $userIds
     * @return Collection<int, array<int, array{start: string, end: string}>>
     */
    private function approvedRangesByUser(string $model, $userIds, CarbonImmutable $startDate, CarbonImmutable $endDate): Collection
    {
        return $model::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString())
            ->get(['user_id', 'start_date', 'end_date'])
            ->groupBy('user_id')
            ->map(fn ($items) => $items
                ->map(fn ($item): array => [
                    'start' => $item->start_date->format('Y-m-d'),
                    'end' => $item->end_date->format('Y-m-d'),
                ])
                ->all());
    }

    /**
     * Resolve the single status for a user on a given date, in priority
     * order: approved dinas, approved sick leave, approved leave,
     * approved permission, attendance, absent.
     *
     * @param  Collection<int, array<int, string>>  $attendances
     * @param  Collection<int, array<int, array{start: string, end: string}>>  $businessTrips
     * @param  Collection<int, array<int, array{start: string, end: string}>>  $sickLeaves
     * @param  Collection<int, array<int, array{start: string, end: string}>>  $leaves
     * @param  Collection<int, array<int, array{start: string, end: string}>>  $permissions
     */
    private function statusFor(
        string $date,
        int $userId,
        Collection $attendances,
        Collection $businessTrips,
        Collection $sickLeaves,
        Collection $leaves,
        Collection $permissions,
    ): string {
        if ($this->rangeCovers($businessTrips->get($userId, []), $date)) {
            return self::DINAS;
        }

        if ($this->rangeCovers($sickLeaves->get($userId, []), $date)) {
            return self::SICK;
        }

        if ($this->rangeCovers($leaves->get($userId, []), $date)) {
            return self::LEAVE;
        }

        if ($this->rangeCovers($permissions->get($userId, []), $date)) {
            return self::PERMISSION;
        }

        if (in_array($date, $attendances->get($userId, []), true)) {
            return self::PRESENT;
        }

        return self::ABSENT;
    }

    /**
     * Whether any approved range covers the given date.
     *
     * @param  array<int, array{start: string, end: string}>  $ranges
     */
    private function rangeCovers(array $ranges, string $date): bool
    {
        foreach ($ranges as $range) {
            if ($date >= $range['start'] && $date <= $range['end']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function dateRange(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $dates = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date = $date->addDay()) {
            $dates[] = $date->format('Y-m-d');
        }

        return $dates;
    }

    /**
     * @param  array<int, array{nip: string, name: string, position: string, dates: array<string, string>}>  $rows
     * @return array{total_users: int, hadir: int, absen: int, izin: int, cuti: int, sakit: int, dinas: int}
     */
    private function summarize(array $rows): array
    {
        $summary = [
            'total_users' => count($rows),
            'hadir' => 0,
            'absen' => 0,
            'izin' => 0,
            'cuti' => 0,
            'sakit' => 0,
            'dinas' => 0,
        ];

        foreach ($rows as $row) {
            foreach ($row['dates'] as $status) {
                $summary['hadir'] += (int) ($status === self::PRESENT);
                $summary['absen'] += (int) ($status === self::ABSENT);
                $summary['izin'] += (int) ($status === self::PERMISSION);
                $summary['cuti'] += (int) ($status === self::LEAVE);
                $summary['sakit'] += (int) ($status === self::SICK);
                $summary['dinas'] += (int) ($status === self::DINAS);
            }
        }

        return $summary;
    }
}

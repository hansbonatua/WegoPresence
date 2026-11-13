<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceComplaint;
use App\Models\BusinessTrip;
use App\Models\LeaveRequest;
use App\Models\Office;
use App\Models\Permission;
use App\Models\SickLeave;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {}

    /**
     * Collect all dashboard data for the authenticated user.
     *
     * @return array{greeting: string, date: string, dashboard_variant: 'admin'|'user', cards: array<int, array{id: string, label: string, value: int}>, charts: array<string, array{labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int>}>}>, activities: array<int, array{id: int, type: string, user_name: string, title: string, status: string|null, created_at: string|null}>, today_attendance: array{check_in_time: string|null, check_out_time: string|null, attendance_status: string|null}|null, attendance_history: array<int, array{id: int, attendance_date: string, check_in_time: string|null, check_out_time: string|null, attendance_status: string|null}>}
     */
    public function getDashboardData(User $user): array
    {
        $isEmployee = $user->isUser();

        return [
            'greeting' => $this->greeting(),
            'date' => today()->translatedFormat('l, d F Y'),
            'dashboard_variant' => $isEmployee ? 'user' : 'admin',
            'cards' => $isEmployee ? [] : $this->cards($user),
            'charts' => $isEmployee ? [] : $this->charts(),
            'activities' => $isEmployee ? [] : $this->activities(),
            'today_attendance' => $isEmployee ? $this->todayAttendance($user) : null,
            'attendance_history' => $isEmployee ? $this->attendanceHistory($user) : [],
        ];
    }

    /**
     * Greeting based on the server time.
     */
    private function greeting(): string
    {
        return match (true) {
            now()->hour < 12 => 'Good Morning',
            now()->hour < 18 => 'Good Afternoon',
            default => 'Good Evening',
        };
    }

    /**
     * Summary statistics displayed as cards. Managers (super admins and
     * admins) see every card aggregated across the whole company.
     *
     * @return array<int, array{id: string, label: string, value: int}>
     */
    private function cards(User $user): array
    {
        // "Today" differs per attendance timezone (WIB/WITA/WIT), while
        // attendance_date stores the local date of the transaction. The set
        // of local dates that can mean "now" holds at most two calendar
        // days, so the aggregation stays small and driver-agnostic.
        $localDates = collect(['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'])
            ->map(fn (string $timezone): string => Carbon::now($timezone)->toDateString())
            ->unique()
            ->sort()
            ->values();

        $groups = Attendance::query()
            ->whereDate('attendance_date', '>=', $localDates->first())
            ->whereDate('attendance_date', '<=', $localDates->last())
            ->selectRaw('attendance_date, attendance_timezone, attendance_status, count(*) as total')
            ->groupBy('attendance_date', 'attendance_timezone', 'attendance_status')
            ->get();

        $attendanceToday = 0;
        $lateToday = 0;

        /** @var Attendance $group */
        foreach ($groups as $group) {
            $recordDate = Carbon::parse((string) $group->getAttribute('attendance_date'))->format('Y-m-d');
            $timezone = AttendanceService::timezoneFor((string) $group->getAttribute('attendance_timezone'));

            if ($recordDate !== Carbon::now($timezone)->toDateString()) {
                continue;
            }

            $total = (int) $group->getAttribute('total');

            $attendanceToday += $total;
            $lateToday += (string) $group->getAttribute('attendance_status') === 'late' ? $total : 0;
        }

        $cards = [
            [
                'id' => 'total_employees',
                'label' => 'Total Employee',
                'value' => User::query()
                    ->where('status', 'active')
                    ->count(),
            ],
            [
                'id' => 'total_offices',
                'label' => 'Total Offices',
                'value' => Office::count(),
            ],
            [
                'id' => 'attendance_today',
                'label' => 'Attendance Today',
                'value' => $attendanceToday,
            ],
            [
                'id' => 'late_today',
                'label' => 'Late Today',
                'value' => $lateToday,
            ],
            [
                'id' => 'leave_today',
                'label' => 'Leave Today',
                'value' => $this->countApprovedRangeToday(LeaveRequest::class),
            ],
            [
                'id' => 'permission_today',
                'label' => 'Permission Today',
                'value' => $this->countApprovedToday(Permission::class, 'start_date'),
            ],
            [
                'id' => 'sick_today',
                'label' => 'Sick Leave Today',
                'value' => $this->countApprovedRangeToday(SickLeave::class),
            ],
            [
                'id' => 'dinas_today',
                'label' => 'Dinas Today',
                'value' => $this->countApprovedRangeToday(BusinessTrip::class),
            ],
            [
                'id' => 'pending_approval',
                'label' => 'Pending Approval',
                'value' => $this->pendingApprovalCount(),
            ],
            [
                'id' => 'pending_registrations',
                'label' => 'Pending Registrations',
                'value' => $this->registrationService->pendingCount($user),
            ],
        ];

        return $cards;
    }

    /**
     * Count approved requests that cover today's date.
     */
    private function countApprovedRangeToday(string $model): int
    {
        /** @var class-string $model */
        return $model::query()
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->count();
    }

    /**
     * Count approved requests that fall on a single date.
     */
    private function countApprovedToday(string $model, string $column): int
    {
        /** @var class-string $model */
        return $model::query()
            ->where('status', 'approved')
            ->whereDate($column, today())
            ->count();
    }

    /**
     * Count requests still waiting for approval.
     */
    private function pendingApprovalCount(): int
    {
        return LeaveRequest::query()->where('status', 'pending')->count()
            + Permission::query()->where('status', 'pending')->count()
            + SickLeave::query()->where('status', 'pending')->count()
            + BusinessTrip::query()->where('status', 'pending')->count()
            + AttendanceComplaint::query()->where('status', 'pending')->count();
    }

    /**
     * Chart datasets. Built from real data where possible; datasets
     * remain empty until the corresponding modules generate data.
     *
     * @return array<string, array{labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int>}>}>
     */
    private function charts(): array
    {
        $lastSevenDays = collect(range(6, 0))
            ->map(fn (int $daysAgo): string => today()->subDays($daysAgo)->format('Y-m-d'));

        // Attendance dates follow the transaction timezone, so the window has
        // to reach one day past the application timezone's today. Rows dated
        // beyond that label are folded into the last bucket below.
        $windowEnd = today()->addDay()->format('Y-m-d');

        $counts = Attendance::query()
            ->whereDate('attendance_date', '>=', $lastSevenDays->first())
            ->whereDate('attendance_date', '<=', $windowEnd)
            ->selectRaw('attendance_date, count(*) as total')
            ->groupBy('attendance_date')
            ->pluck('total', 'attendance_date')
            ->mapWithKeys(fn ($total, $date): array => [
                Carbon::parse((string) $date)->format('Y-m-d') => (int) $total,
            ]);

        $todayLabel = $lastSevenDays->last();

        $trend = $lastSevenDays
            ->map(fn (string $date): int => (int) ($counts[$date] ?? 0))
            ->values()
            ->all();

        foreach ($counts as $date => $total) {
            if ($date > $todayLabel) {
                $trend[count($trend) - 1] += (int) $total;
            }
        }

        return [
            'attendance_trend' => [
                'labels' => $lastSevenDays->values()->all(),
                'datasets' => [
                    [
                        'label' => 'Attendances',
                        'data' => $trend,
                    ],
                ],
            ],
        ];
    }

    /**
     * Recent activity feed, merging attendance, leave, permission and
     * complaint events, newest first.
     *
     * @return array<int, array{id: int, type: string, user_name: string, title: string, status: string|null, created_at: string|null}>
     */
    private function activities(): array
    {
        return $this->attendanceActivities()
            ->concat($this->leaveActivities())
            ->concat($this->permissionActivities())
            ->concat($this->sickLeaveActivities())
            ->concat($this->businessTripActivities())
            ->concat($this->complaintActivities())
            ->sortByDesc('created_at')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{id: int, type: string, user_name: string, title: string, status: string|null, created_at: string|null}>
     */
    private function attendanceActivities(): Collection
    {
        return Attendance::query()
            ->with('user:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Attendance $attendance): array => [
                'id' => $attendance->id,
                'type' => 'attendance',
                'user_name' => $attendance->user?->name ?? 'Unknown user',
                'title' => $attendance->check_in_time
                    ? 'Checked in at '.$attendance->check_in_time->format('H:i')
                    : 'Attendance recorded',
                'status' => $attendance->attendance_status,
                'created_at' => $attendance->created_at?->toDateTimeString(),
            ]);
    }

    /**
     * @return Collection<int, array{id: int, type: string, user_name: string, title: string, status: string|null, created_at: string|null}>
     */
    private function leaveActivities(): Collection
    {
        return LeaveRequest::query()
            ->with('user:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (LeaveRequest $leave): array => [
                'id' => $leave->id,
                'type' => 'leave',
                'user_name' => $leave->user?->name ?? 'Unknown user',
                'title' => 'Leave: '.$leave->start_date.' → '.$leave->end_date,
                'status' => $leave->status,
                'created_at' => $leave->created_at?->toDateTimeString(),
            ]);
    }

    /**
     * @return Collection<int, array{id: int, type: string, user_name: string, title: string, status: string|null, created_at: string|null}>
     */
    private function permissionActivities(): Collection
    {
        return Permission::query()
            ->with('user:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Permission $permission): array => [
                'id' => $permission->id,
                'type' => 'permission',
                'user_name' => $permission->user?->name ?? 'Unknown user',
                'title' => 'Permission: '.$permission->start_date->format('Y-m-d'),
                'status' => $permission->status,
                'created_at' => $permission->created_at?->toDateTimeString(),
            ]);
    }

    /**
     * @return Collection<int, array{id: int, type: string, user_name: string, title: string, status: string|null, created_at: string|null}>
     */
    private function sickLeaveActivities(): Collection
    {
        return SickLeave::query()
            ->with('user:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (SickLeave $sickLeave): array => [
                'id' => $sickLeave->id,
                'type' => 'sick_leave',
                'user_name' => $sickLeave->user?->name ?? 'Unknown user',
                'title' => 'Sick leave: '.$sickLeave->start_date->format('Y-m-d').' → '.$sickLeave->end_date->format('Y-m-d'),
                'status' => $sickLeave->status,
                'created_at' => $sickLeave->created_at?->toDateTimeString(),
            ]);
    }

    /**
     * @return Collection<int, array{id: int, type: string, user_name: string, title: string, status: string|null, created_at: string|null}>
     */
    private function complaintActivities(): Collection
    {
        return AttendanceComplaint::query()
            ->with('user:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (AttendanceComplaint $complaint): array => [
                'id' => $complaint->id,
                'type' => 'complaint',
                'user_name' => $complaint->user?->name ?? 'Unknown user',
                'title' => 'Attendance complaint submitted',
                'status' => $complaint->status,
                'created_at' => $complaint->created_at?->toDateTimeString(),
            ]);
    }

    /**
     * @return Collection<int, array{id: int, type: string, user_name: string, title: string, status: string|null, created_at: string|null}>
     */
    private function businessTripActivities(): Collection
    {
        return BusinessTrip::query()
            ->with('user:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (BusinessTrip $trip): array => [
                'id' => $trip->id,
                'type' => 'business_trip',
                'user_name' => $trip->user?->name ?? 'Unknown user',
                'title' => 'Business trip: '.$trip->destination.' ('.$trip->start_date->format('Y-m-d').' → '.$trip->end_date->format('Y-m-d').')',
                'status' => $trip->status,
                'created_at' => $trip->created_at?->toDateTimeString(),
            ]);
    }

    /**
     * Today's check-in and check-out details for an employee.
     *
     * @return array{check_in_time: string|null, check_out_time: string|null, attendance_status: string|null}
     */
    private function todayAttendance(User $user): array
    {
        // Reuse the attendance service so WITA/WIT records stay visible
        // after midnight in the application timezone.
        $attendance = app(AttendanceService::class)->todayFor($user);

        return [
            'check_in_time' => $attendance?->check_in_time?->format('H:i'),
            'check_out_time' => $attendance?->check_out_time?->format('H:i'),
            'attendance_status' => $attendance?->attendance_status,
        ];
    }

    /**
     * Last five attendance records of an employee.
     *
     * @return array<int, array{id: int, attendance_date: string, check_in_time: string|null, check_out_time: string|null, attendance_status: string|null}>
     */
    private function attendanceHistory(User $user): array
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->latest('attendance_date')
            ->limit(5)
            ->get()
            ->map(fn (Attendance $attendance): array => [
                'id' => $attendance->id,
                'attendance_date' => $attendance->attendance_date->format('Y-m-d'),
                'check_in_time' => $attendance->check_in_time?->format('H:i'),
                'check_out_time' => $attendance->check_out_time?->format('H:i'),
                'attendance_status' => $attendance->attendance_status,
            ])
            ->all();
    }
}

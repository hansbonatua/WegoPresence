<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\BusinessTrip;
use App\Models\LeaveRequest;
use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SickLeave;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceSummaryTest extends TestCase
{
    use RefreshDatabase;

    private static int $officeSequence = 0;

    public function test_admin_can_open_the_summary(): void
    {
        $admin = $this->createManager('admin');

        $response = $this->actingAs($admin)->get(route('attendance.summary'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('attendance/summary')
                ->has('dates')
                ->has('users')
                ->has('summary'));
    }

    public function test_regular_users_are_forbidden(): void
    {
        $user = $this->createUser('user');

        $this->actingAs($user)->get(route('attendance.summary'))->assertForbidden();
    }

    public function test_super_admins_are_forbidden(): void
    {
        $super = $this->createManager('super_admin');

        $this->actingAs($super)->get(route('attendance.summary'))->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('attendance.summary'))->assertRedirect(route('login'));
    }

    public function test_admin_only_sees_users_from_their_own_office(): void
    {
        $adminOffice = $this->createOffice();
        $otherOffice = $this->createOffice();
        $admin = $this->createManager('admin', ['office_id' => $adminOffice->id]);
        $ownUser = $this->createUser('user', ['office_id' => $adminOffice->id, 'nip' => '010001']);
        $otherUser = $this->createUser('user', ['office_id' => $otherOffice->id, 'nip' => '010002']);

        $response = $this->actingAs($admin)->get(route('attendance.summary'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('attendance/summary')
                ->has('users', 1)
                ->where('users.0.nip', $ownUser->nip));

        $this->assertNotSame($ownUser->nip, $otherUser->nip);
    }

    public function test_admin_cannot_bypass_the_office_scope(): void
    {
        $adminOffice = $this->createOffice();
        $otherOffice = $this->createOffice();
        $admin = $this->createManager('admin', ['office_id' => $adminOffice->id]);
        $this->createUser('user', ['office_id' => $otherOffice->id, 'nip' => '010002']);

        $response = $this->actingAs($admin)->get(route('attendance.summary', [
            'office_id' => $otherOffice->id,
        ]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('attendance/summary')
                ->has('users', 0));
    }

    public function test_saturday_attendance_shows_present(): void
    {
        [$start, $saturday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createAttendance($user, $saturday);

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->copy()->addDays(6)->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$saturday->format('Y-m-d'), 'H'));
    }

    public function test_saturday_without_attendance_shows_absent(): void
    {
        [$start, $saturday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $saturday->format('Y-m-d'),
            'end_date' => $saturday->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$saturday->format('Y-m-d'), 'A'));
    }

    public function test_sunday_attendance_shows_present(): void
    {
        [$start, , $sunday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createAttendance($user, $sunday);

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->copy()->addDays(6)->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$sunday->format('Y-m-d'), 'H'));
    }

    public function test_sunday_without_attendance_shows_absent(): void
    {
        [$start, , $sunday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $sunday->format('Y-m-d'),
            'end_date' => $sunday->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$sunday->format('Y-m-d'), 'A'));
    }

    public function test_saturday_sick_leave_shows_sick(): void
    {
        [$start, $saturday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createSickLeave($user, $saturday, 'approved');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $saturday->format('Y-m-d'),
            'end_date' => $saturday->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$saturday->format('Y-m-d'), 'S'));
    }

    public function test_sunday_sick_leave_shows_sick(): void
    {
        [$start, , $sunday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createSickLeave($user, $sunday, 'approved');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $sunday->format('Y-m-d'),
            'end_date' => $sunday->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$sunday->format('Y-m-d'), 'S'));
    }

    public function test_saturday_leave_shows_leave(): void
    {
        [$start, $saturday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createLeave($user, $saturday, 'approved');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $saturday->format('Y-m-d'),
            'end_date' => $saturday->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$saturday->format('Y-m-d'), 'C'));
    }

    public function test_sunday_leave_shows_leave(): void
    {
        [$start, , $sunday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createLeave($user, $sunday, 'approved');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $sunday->format('Y-m-d'),
            'end_date' => $sunday->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$sunday->format('Y-m-d'), 'C'));
    }

    public function test_saturday_permission_shows_permission(): void
    {
        [$start, $saturday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createPermission($user, $saturday, 'approved');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $saturday->format('Y-m-d'),
            'end_date' => $saturday->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$saturday->format('Y-m-d'), 'I'));
    }

    public function test_sunday_permission_shows_permission(): void
    {
        [$start, , $sunday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createPermission($user, $sunday, 'approved');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $sunday->format('Y-m-d'),
            'end_date' => $sunday->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$sunday->format('Y-m-d'), 'I'));
    }

    public function test_pending_sick_leave_does_not_override_absent(): void
    {
        [$start, $saturday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createSickLeave($user, $saturday, 'pending');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $saturday->format('Y-m-d'),
            'end_date' => $saturday->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$saturday->format('Y-m-d'), 'A'));
    }

    public function test_attendance_produces_present(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createAttendance($user, $start);

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'H'));
    }

    public function test_status_priority_resolved_in_order(): void
    {
        [$start, $saturday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createAttendance($user, $saturday);
        $this->createSickLeave($user, $saturday, 'approved');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $saturday->format('Y-m-d'),
            'end_date' => $saturday->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$saturday->format('Y-m-d'), 'S'));
    }

    public function test_missing_attendance_produces_absent(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $this->createUser('user', ['office_id' => $admin->office_id]);

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'A'));
    }

    public function test_approved_permission_produces_permission(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createPermission($user, $start, 'approved');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'I'));
    }

    public function test_pending_permission_does_not_produce_permission(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createPermission($user, $start, 'pending');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'A'));
    }

    public function test_approved_leave_produces_leave(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createLeave($user, $start, 'approved');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'C'));
    }

    public function test_pending_leave_does_not_produce_leave(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createLeave($user, $start, 'pending');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'A'));
    }

    public function test_approved_sick_leave_produces_sick(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createSickLeave($user, $start, 'approved');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'S'));
    }

    public function test_pending_sick_leave_does_not_produce_sick(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createSickLeave($user, $start, 'pending');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'A'));
    }

    public function test_status_priority_is_resolved_in_order(): void
    {
        [$start, , , $thursday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);

        $this->createSickLeave($user, $start, 'approved');
        $this->createLeave($user, $thursday, 'approved');
        $this->createPermission($user, $start->copy()->addDays(2), 'approved');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->copy()->addDays(4)->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'S')
            ->where('users.0.dates.'.$start->copy()->addDays(2)->format('Y-m-d'), 'I')
            ->where('users.0.dates.'.$thursday->format('Y-m-d'), 'C'));
    }

    public function test_approved_status_beats_attendance(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createAttendance($user, $start);
        $this->createSickLeave($user, $start, 'approved');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'S'));
    }

    public function test_cancelled_statuses_are_not_counted(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createAttendance($user, $start);
        $this->createPermission($user, $start, 'cancelled');
        $this->createLeave($user, $start, 'rejected');
        $this->createSickLeave($user, $start, 'rejected');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'H'));
    }

    public function test_date_range_validation_requires_valid_dates(): void
    {
        $admin = $this->createManager('admin');

        $response = $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => 'not-a-date',
            'end_date' => '2026-08-10',
        ]));

        $response->assertSessionHasErrors('start_date');
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $admin = $this->createManager('admin');

        $response = $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-01',
        ]));

        $response->assertSessionHasErrors('end_date');
    }

    public function test_ranges_longer_than_31_days_are_rejected(): void
    {
        $admin = $this->createManager('admin');

        $response = $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => '2026-08-01',
            'end_date' => '2026-09-02',
        ]));

        $response->assertSessionHasErrors('end_date');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]))->assertOk();
    }

    public function test_summary_counts_are_correct(): void
    {
        [$start, , , $thursday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $userA = $this->createUser('user', ['office_id' => $admin->office_id]);
        $userB = $this->createUser('user', ['office_id' => $admin->office_id]);

        $this->createAttendance($userA, $start);
        $this->createSickLeave($userA, $start->copy()->addDays(1), 'approved');
        $this->createLeave($userA, $thursday, 'approved');

        $response = $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->copy()->addDays(4)->format('Y-m-d'),
        ]));

        $response->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('summary.total_users', 2)
            ->where('summary.hadir', 1)
            ->where('summary.sakit', 1)
            ->where('summary.cuti', 1)
            ->where('summary.absen', 7)
            ->where('summary.izin', 0)
            ->where('summary.dinas', 0));
    }

    public function test_summary_does_not_suffer_from_n_plus_one_queries(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');

        foreach (range(1, 5) as $i) {
            $user = $this->createUser('user', [
                'office_id' => $admin->office_id,
                'nip' => '010'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            ]);
            $this->createAttendance($user, $start);
            $this->createPermission($user, $start->copy()->addDays(1), 'approved');
            $this->createLeave($user, $start->copy()->addDays(2), 'approved');
            $this->createSickLeave($user, $start->copy()->addDays(3), 'approved');
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->copy()->addDays(6)->format('Y-m-d'),
        ]))->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(10, $queryCount);
    }

    public function test_empty_state_returns_no_users(): void
    {
        $admin = $this->createManager('admin');

        $response = $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-07',
        ]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('attendance/summary')
                ->where('summary.total_users', 0)
                ->where('users', []));
    }

    public function test_date_filters_change_the_visible_period(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $adminOfficeId = $admin->office_id;
        $user = User::factory()->create(['office_id' => $adminOfficeId, 'nip' => '010001']);
        $this->createAttendance($user, $start->copy()->addDays(2));

        $short = $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
        ]));

        $short->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('attendance/summary')
                ->where('dates', [$start->format('Y-m-d')])
                ->where('users.0.dates.'.$start->format('Y-m-d'), 'A'));

        $long = $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->copy()->addDays(4)->format('Y-m-d'),
        ]));

        $long->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('attendance/summary')
                ->has('dates', 5)
                ->where('users.0.dates.'.$start->copy()->addDays(2)->format('Y-m-d'), 'H'));
    }

    public function test_default_range_is_current_month_to_today(): void
    {
        $admin = $this->createManager('admin');

        $response = $this->actingAs($admin)->get(route('attendance.summary'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('attendance/summary')
                ->where('filters.start_date', now()->startOfMonth()->toDateString())
                ->where('filters.end_date', now()->toDateString()));
    }

    public function test_multi_day_ranges_cover_every_day_in_between(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createLeave($user, $start->copy()->addDays(2), 'approved');
        $this->createAttendance($user, $start->copy()->addDays(3));

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->copy()->addDays(4)->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->copy()->addDays(2)->format('Y-m-d'), 'C')
            ->where('users.0.dates.'.$start->copy()->addDays(3)->format('Y-m-d'), 'H'));
    }

    public function test_approved_dinas_on_weekday_shows_d(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createBusinessTrip($user, $start, 'approved');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'D'));
    }

    public function test_approved_dinas_on_saturday_shows_d(): void
    {
        [$start, $saturday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createBusinessTrip($user, $saturday, 'approved');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $saturday->format('Y-m-d'),
            'end_date' => $saturday->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$saturday->format('Y-m-d'), 'D'));
    }

    public function test_approved_dinas_on_sunday_shows_d(): void
    {
        [$start, , $sunday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createBusinessTrip($user, $sunday, 'approved');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $sunday->format('Y-m-d'),
            'end_date' => $sunday->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$sunday->format('Y-m-d'), 'D'));
    }

    public function test_pending_dinas_does_not_produce_d(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createBusinessTrip($user, $start, 'pending');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'A'));
    }

    public function test_rejected_dinas_does_not_produce_d(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createBusinessTrip($user, $start, 'rejected');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'A'));
    }

    public function test_cancelled_dinas_does_not_produce_d(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createBusinessTrip($user, $start, 'cancelled');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'A'));
    }

    public function test_multi_day_dinas_produces_d_for_every_date(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createBusinessTrip($user, $start, 'approved', $start->copy()->addDays(2));

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->copy()->addDays(4)->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'D')
            ->where('users.0.dates.'.$start->copy()->addDays(1)->format('Y-m-d'), 'D')
            ->where('users.0.dates.'.$start->copy()->addDays(2)->format('Y-m-d'), 'D')
            ->where('users.0.dates.'.$start->copy()->addDays(3)->format('Y-m-d'), 'A'));
    }

    public function test_dinas_outside_date_range_does_not_produce_d(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createBusinessTrip($user, $start->copy()->addDays(10), 'approved', $start->copy()->addDays(12));

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->copy()->addDays(4)->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'A'));
    }

    public function test_dinas_overrides_attendance(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createAttendance($user, $start);
        $this->createBusinessTrip($user, $start, 'approved');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'D'));
    }

    public function test_dinas_overrides_sick_leave_and_leave_and_permission(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createSickLeave($user, $start, 'approved');
        $this->createLeave($user, $start, 'approved');
        $this->createPermission($user, $start, 'approved');
        $this->createBusinessTrip($user, $start, 'approved');

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'D'));
    }

    public function test_absence_without_dinas_remains_a(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);

        $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
        ]))->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('users.0.dates.'.$start->format('Y-m-d'), 'A'));
    }

    public function test_summary_card_dinas_count_is_correct(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $userA = $this->createUser('user', ['office_id' => $admin->office_id]);
        $userB = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createBusinessTrip($userA, $start, 'approved');
        $this->createBusinessTrip($userA, $start->copy()->addDays(1), 'approved');
        $this->createAttendance($userB, $start);

        $response = $this->actingAs($admin)->get(route('attendance.summary', [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->copy()->addDays(1)->format('Y-m-d'),
        ]));

        $response->assertInertia(fn ($page) => $page
            ->component('attendance/summary')
            ->where('summary.dinas', 2)
            ->where('summary.hadir', 1));
    }

    private function createOffice(): Office
    {
        self::$officeSequence++;

        return Office::query()->create([
            'office_code' => 'JKT'.str_pad((string) self::$officeSequence, 3, '0', STR_PAD_LEFT),
            'office_name' => 'Office '.self::$officeSequence,
            'city' => 'Jakarta',
            'address' => 'Jl. Test '.self::$officeSequence,
            'status' => 'active',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
    }

    private function createManager(string $roleName, array $attributes = []): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => $roleName], ['name' => $roleName])->id,
            'status' => 'active',
            ...$attributes,
        ]);
    }

    private function createUser(string $roleName, array $attributes = []): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => $roleName], ['name' => $roleName])->id,
            'status' => 'active',
            ...$attributes,
        ]);
    }

    private function createAttendance(User $user, CarbonImmutable $date, array $overrides = []): Attendance
    {
        return Attendance::query()->create([
            'user_id' => $user->id,
            'attendance_date' => $date->toDateString(),
            'check_in_time' => '08:00:00',
            'attendance_status' => 'on_time',
            ...$overrides,
        ]);
    }

    private function createPermission(User $user, CarbonImmutable $date, string $status): Permission
    {
        return Permission::query()->create([
            'user_id' => $user->id,
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'reason' => 'Smoke permission',
            'status' => $status,
        ]);
    }

    private function createLeave(User $user, CarbonImmutable $date, string $status): LeaveRequest
    {
        return LeaveRequest::query()->create([
            'user_id' => $user->id,
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'reason' => 'Smoke leave',
            'status' => $status,
        ]);
    }

    private function createSickLeave(User $user, CarbonImmutable $date, string $status): SickLeave
    {
        return SickLeave::query()->create([
            'user_id' => $user->id,
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'reason' => 'Smoke sick leave',
            'status' => $status,
        ]);
    }

    private function createBusinessTrip(User $user, CarbonImmutable $startDate, string $status, ?CarbonImmutable $endDate = null): BusinessTrip
    {
        return BusinessTrip::query()->create([
            'user_id' => $user->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => ($endDate ?? $startDate)->toDateString(),
            'destination' => 'Bandung',
            'purpose' => 'Client meeting',
            'status' => $status,
        ]);
    }

    /**
     * A Monday-based working week and the weekend dates.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable, 3: CarbonImmutable}
     */
    private function mondayBasedWeek(): array
    {
        $monday = now()->startOfWeek(CarbonImmutable::MONDAY);

        return [$monday, $monday->addDays(5), $monday->addDays(6), $monday->addDays(3)];
    }
}

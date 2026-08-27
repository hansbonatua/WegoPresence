<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AttendanceRecapTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_recap_with_all_offices(): void
    {
        $officeA = $this->createOffice('JKT001', 'Jakarta Office');
        $officeB = $this->createOffice('BDO001', 'Bandung Office');
        $userA = $this->createUser('user', ['office_id' => $officeA->id]);
        $userB = $this->createUser('user', ['office_id' => $officeB->id]);
        $this->createAttendance($userA, ['attendance_status' => 'on_time']);
        $this->createAttendance($userB, ['attendance_status' => 'late']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('attendance/recap')
            ->where('recaps.total', 2)
            ->has('recaps.data', 2)
            ->has('offices', 2));
    }

    public function test_admin_can_view_recap_for_their_office_only(): void
    {
        $officeA = $this->createOffice('JKT001', 'Jakarta Office');
        $officeB = $this->createOffice('BDO001', 'Bandung Office');
        $staff = $this->createUser('user', ['office_id' => $officeA->id]);
        $other = $this->createUser('user', ['office_id' => $officeB->id]);
        $this->createAttendance($staff);
        $this->createAttendance($other);
        $admin = $this->createUser('admin', ['office_id' => $officeA->id]);

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('recaps.total', 1)
            ->has('recaps.data', 1, fn ($item) => $item
                ->where('user.nip', $staff->nip)
                ->etc()));
    }

    public function test_admin_cannot_view_other_office_attendance(): void
    {
        $officeA = $this->createOffice('JKT001', 'Jakarta Office');
        $officeB = $this->createOffice('BDO001', 'Bandung Office');
        $other = $this->createUser('user', ['office_id' => $officeB->id]);
        $this->createAttendance($other);
        $admin = $this->createUser('admin', ['office_id' => $officeA->id]);

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('recaps.total', 0));
    }

    public function test_regular_user_cannot_access_recap(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->get(route('attendance.recap'));

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('attendance.recap'));

        $response->assertRedirect(route('login'));
    }

    public function test_recap_policy_only_allows_managers(): void
    {
        $admin = $this->createUser('admin');
        $regular = $this->createUser('user');

        $this->assertFalse(Gate::forUser($admin)->denies('recap', Attendance::class));
        $this->assertTrue(Gate::forUser($regular)->denies('recap', Attendance::class));
    }

    public function test_recap_is_filtered_by_date_range(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['attendance_date' => '2026-08-01']);
        $this->createAttendance($user, ['attendance_date' => '2026-08-05']);
        $this->createAttendance($user, ['attendance_date' => '2026-08-10']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(
            route('attendance.recap', ['start_date' => '2026-08-02', 'end_date' => '2026-08-08']),
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('recaps.total', 1)
            ->where('recaps.data.0.attendance_date', '2026-08-05')
            ->where('filters.start_date', '2026-08-02')
            ->where('filters.end_date', '2026-08-08'));
    }

    public function test_recap_is_filtered_by_nip_search(): void
    {
        $user = $this->createUser('user', ['nip' => '1234567890']);
        $other = $this->createUser('user', ['nip' => '9876543210']);
        $this->createAttendance($user);
        $this->createAttendance($other);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(
            route('attendance.recap', ['nip' => '2345']),
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('recaps.total', 1)
            ->where('recaps.data.0.user.nip', '1234567890'));
    }

    public function test_recap_is_filtered_by_name_search(): void
    {
        $user = $this->createUser('user', ['name' => 'Budi Santoso']);
        $other = $this->createUser('user', ['name' => 'Siti Aminah']);
        $this->createAttendance($user);
        $this->createAttendance($other);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(
            route('attendance.recap', ['name' => 'budi']),
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('recaps.total', 1)
            ->where('recaps.data.0.user.name', 'Budi Santoso'));
    }

    public function test_recap_is_filtered_by_office(): void
    {
        $officeA = $this->createOffice('JKT001', 'Jakarta Office');
        $officeB = $this->createOffice('BDO001', 'Bandung Office');
        $userA = $this->createUser('user', ['office_id' => $officeA->id]);
        $userB = $this->createUser('user', ['office_id' => $officeB->id]);
        $this->createAttendance($userA);
        $this->createAttendance($userB);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(
            route('attendance.recap', ['office_id' => $officeB->id]),
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('recaps.total', 1)
            ->where('recaps.data.0.office.id', $officeB->id));
    }

    public function test_recap_is_filtered_by_attendance_status(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['attendance_status' => 'on_time']);
        $this->createAttendance($user, [
            'attendance_date' => '2026-08-06',
            'check_in_time' => '08:30:00',
            'attendance_status' => 'late',
        ]);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(
            route('attendance.recap', ['attendance_status' => 'late']),
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('recaps.total', 1)
            ->where('recaps.data.0.attendance_status', 'late'));
    }

    public function test_recap_rejects_end_date_before_start_date(): void
    {
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(
            route('attendance.recap', ['start_date' => '2026-08-10', 'end_date' => '2026-08-01']),
        );

        $response->assertSessionHasErrors('end_date');
    }

    public function test_recap_rejects_invalid_office_id(): void
    {
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(
            route('attendance.recap', ['office_id' => 999999]),
        );

        $response->assertSessionHasErrors('office_id');
    }

    public function test_recap_rejects_invalid_attendance_status(): void
    {
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(
            route('attendance.recap', ['attendance_status' => 'invalid']),
        );

        $response->assertSessionHasErrors('attendance_status');
    }

    public function test_recap_is_paginated_by_fifteen(): void
    {
        $user = $this->createUser('user');
        $admin = $this->createUser('super_admin');

        for ($day = 1; $day <= 16; $day++) {
            $this->createAttendance($user, [
                'attendance_date' => sprintf('2026-08-%02d', $day),
            ]);
        }

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('recaps.total', 16)
            ->where('recaps.current_page', 1)
            ->where('recaps.last_page', 2)
            ->has('recaps.data', 15));
    }

    public function test_recap_is_sorted_by_date_descending(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['attendance_date' => '2026-08-03']);
        $this->createAttendance($user, ['attendance_date' => '2026-08-01']);
        $this->createAttendance($user, ['attendance_date' => '2026-08-02']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->where('recaps.data.0.attendance_date', '2026-08-03')
            ->where('recaps.data.1.attendance_date', '2026-08-02')
            ->where('recaps.data.2.attendance_date', '2026-08-01'));
    }

    public function test_recap_includes_coordinates_and_null_check_out(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, [
            'latitude' => '-6.1666667',
            'longitude' => '106.8000000',
            'check_out_time' => null,
        ]);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->has('recaps.data', 1, fn ($item) => $item
                ->where('latitude', '-6.1666667')
                ->where('longitude', '106.8000000')
                ->where('check_out_time', null)
                ->etc()));
    }

    public function test_check_in_at_07_50_shows_on_time_with_zero_late_minutes(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['check_in_time' => '07:50:00', 'attendance_status' => 'on_time']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->where('recaps.data.0.attendance_status', 'on_time')
            ->where('recaps.data.0.late_minutes', 0));
    }

    public function test_check_in_at_07_55_shows_on_time_with_zero_late_minutes(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['check_in_time' => '07:55:00', 'attendance_status' => 'on_time']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->where('recaps.data.0.attendance_status', 'on_time')
            ->where('recaps.data.0.late_minutes', 0));
    }

    public function test_check_in_at_07_55_01_shows_on_time_with_zero_late_minutes(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['check_in_time' => '07:55:01', 'attendance_status' => 'on_time']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->where('recaps.data.0.attendance_status', 'on_time')
            ->where('recaps.data.0.late_minutes', 0));
    }

    public function test_check_in_at_07_55_30_shows_on_time_with_zero_late_minutes(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['check_in_time' => '07:55:30', 'attendance_status' => 'on_time']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->where('recaps.data.0.attendance_status', 'on_time')
            ->where('recaps.data.0.late_minutes', 0));
    }

    public function test_check_in_at_07_55_59_shows_on_time_with_zero_late_minutes(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['check_in_time' => '07:55:59', 'attendance_status' => 'late']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->where('recaps.data.0.attendance_status', 'on_time')
            ->where('recaps.data.0.late_minutes', 0));
    }

    public function test_check_in_at_07_56_shows_late_with_1_minute(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['check_in_time' => '07:56:00', 'attendance_status' => 'late']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->where('recaps.data.0.attendance_status', 'late')
            ->where('recaps.data.0.late_minutes', 1));
    }

    public function test_check_in_at_07_56_01_shows_late_with_1_minute(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['check_in_time' => '07:56:01', 'attendance_status' => 'late']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->where('recaps.data.0.attendance_status', 'late')
            ->where('recaps.data.0.late_minutes', 1));
    }

    public function test_check_in_at_07_56_30_shows_late_with_1_minute(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['check_in_time' => '07:56:30', 'attendance_status' => 'late']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->where('recaps.data.0.attendance_status', 'late')
            ->where('recaps.data.0.late_minutes', 1));
    }

    public function test_check_in_at_07_56_59_shows_late_with_1_minute(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['check_in_time' => '07:56:59', 'attendance_status' => 'late']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->where('recaps.data.0.attendance_status', 'late')
            ->where('recaps.data.0.late_minutes', 1));
    }

    public function test_check_in_at_07_57_00_shows_late_with_2_minutes(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['check_in_time' => '07:57:00', 'attendance_status' => 'late']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->where('recaps.data.0.attendance_status', 'late')
            ->where('recaps.data.0.late_minutes', 2));
    }

    public function test_check_in_at_07_58_00_shows_late_with_3_minutes(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['check_in_time' => '07:58:00', 'attendance_status' => 'late']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->where('recaps.data.0.attendance_status', 'late')
            ->where('recaps.data.0.late_minutes', 3));
    }

    public function test_check_in_at_07_58_59_shows_late_with_3_minutes(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['check_in_time' => '07:58:59', 'attendance_status' => 'late']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->where('recaps.data.0.attendance_status', 'late')
            ->where('recaps.data.0.late_minutes', 3));
    }

    public function test_check_in_at_08_00_shows_late_with_5_minutes(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['check_in_time' => '08:00:00', 'attendance_status' => 'late']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->where('recaps.data.0.attendance_status', 'late')
            ->where('recaps.data.0.late_minutes', 5));
    }

    public function test_check_in_at_08_15_shows_late_with_20_minutes(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['check_in_time' => '08:15:00', 'attendance_status' => 'late']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->where('recaps.data.0.attendance_status', 'late')
            ->where('recaps.data.0.late_minutes', 20));
    }

    public function test_check_in_at_09_00_shows_late_with_65_minutes(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['check_in_time' => '09:00:00', 'attendance_status' => 'late']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->where('recaps.data.0.attendance_status', 'late')
            ->where('recaps.data.0.late_minutes', 65));
    }

    public function test_attendance_without_check_in_time_has_null_late_minutes(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, [
            'check_in_time' => null,
            'attendance_status' => 'absent',
        ]);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->where('recaps.data.0.attendance_status', null)
            ->where('recaps.data.0.late_minutes', null));
    }

    public function test_recap_uses_recap_cutoff_not_office_start_time(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, [
            'check_in_time' => '07:58:00',
            'attendance_status' => 'on_time',
        ]);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap'));

        $response->assertInertia(fn ($page) => $page
            ->where('recaps.data.0.attendance_status', 'late')
            ->where('recaps.data.0.late_minutes', 3));
    }

    private function createOffice(string $code, string $name): Office
    {
        return Office::query()->create([
            'office_code' => $code,
            'office_name' => $name,
            'city' => 'Jakarta',
            'address' => 'Jalan Contoh No. 1',
            'status' => 'active',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(string $roleName, array $attributes = []): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['name' => $roleName]);

        return User::factory()->create(['role_id' => $role->id, ...$attributes]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createAttendance(User $user, array $overrides = []): Attendance
    {
        return Attendance::query()->create([...[
            'user_id' => $user->id,
            'attendance_date' => '2026-08-05',
            'check_in_time' => '07:55:00',
            'attendance_status' => 'on_time',
        ], ...$overrides]);
    }
}

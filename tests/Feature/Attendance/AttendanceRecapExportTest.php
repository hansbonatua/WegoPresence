<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class AttendanceRecapExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_export_excel(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap.export.excel'));

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type'),
        );
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('attendance-recap-'.now()->format('Y-m-d').'.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_admin_can_export_excel_for_their_office(): void
    {
        $office = $this->createOffice('JKT001', 'Jakarta Office');
        $staff = $this->createUser('user', ['office_id' => $office->id, 'nip' => '1234567890']);
        $this->createAttendance($staff);
        $admin = $this->createUser('admin', ['office_id' => $office->id]);

        $response = $this->actingAs($admin)->get(route('attendance.recap.export.excel'));

        $response->assertOk();

        $rows = $this->parseXlsx($response);
        $this->assertCount(2, $rows);
        $this->assertSame('1234567890', $rows[1][1]);
        $this->assertSame('Jakarta Office', $rows[1][4]);
    }

    public function test_regular_user_cannot_export(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->get(route('attendance.recap.export.excel'));

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('attendance.recap.export.pdf'));

        $response->assertRedirect(route('login'));
    }

    public function test_super_admin_can_export_pdf(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap.export.pdf'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('attendance-recap-'.now()->format('Y-m-d').'.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
    }

    public function test_admin_can_export_pdf(): void
    {
        $office = $this->createOffice('JKT001', 'Jakarta Office');
        $staff = $this->createUser('user', ['office_id' => $office->id]);
        $this->createAttendance($staff);
        $admin = $this->createUser('admin', ['office_id' => $office->id]);

        $response = $this->actingAs($admin)->get(route('attendance.recap.export.pdf'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_admin_export_excludes_other_office_attendance(): void
    {
        $officeA = $this->createOffice('JKT001', 'Jakarta Office');
        $officeB = $this->createOffice('BDO001', 'Bandung Office');
        $staff = $this->createUser('user', ['office_id' => $officeA->id, 'nip' => '1111111111']);
        $other = $this->createUser('user', ['office_id' => $officeB->id, 'nip' => '9999999999']);
        $this->createAttendance($staff);
        $this->createAttendance($other);
        $admin = $this->createUser('admin', ['office_id' => $officeA->id]);

        $response = $this->actingAs($admin)->get(route('attendance.recap.export.excel'));

        $response->assertOk();

        $rows = $this->parseXlsx($response);
        $nipps = array_column($rows, 1);
        $this->assertContains('1111111111', $nipps);
        $this->assertNotContains('9999999999', $nipps);
    }

    public function test_admin_cannot_filter_in_other_office_data(): void
    {
        $officeA = $this->createOffice('JKT001', 'Jakarta Office');
        $officeB = $this->createOffice('BDO001', 'Bandung Office');
        $staff = $this->createUser('user', ['office_id' => $officeA->id, 'nip' => '1111111111']);
        $other = $this->createUser('user', ['office_id' => $officeB->id, 'nip' => '9999999999']);
        $this->createAttendance($staff);
        $this->createAttendance($other);
        $admin = $this->createUser('admin', ['office_id' => $officeA->id]);

        $response = $this->actingAs($admin)->get(
            route('attendance.recap.export.excel', ['office_id' => $officeB->id]),
        );

        $response->assertOk();

        $rows = $this->parseXlsx($response);
        $nipps = array_column($rows, 1);
        $this->assertNotContains('9999999999', $nipps);
    }

    public function test_excel_export_applies_date_range_filter(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, ['attendance_date' => '2026-08-01']);
        $this->createAttendance($user, ['attendance_date' => '2026-08-05']);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(
            route('attendance.recap.export.excel', [
                'start_date' => '2026-08-02',
                'end_date' => '2026-08-08',
            ]),
        );

        $rows = $this->parseXlsx($response);
        $this->assertCount(2, $rows);
        $this->assertSame('2026-08-05', $rows[1][5]);
    }

    public function test_excel_export_applies_status_filter(): void
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
            route('attendance.recap.export.excel', ['attendance_status' => 'late']),
        );

        $rows = $this->parseXlsx($response);
        $this->assertCount(2, $rows);
        $this->assertSame('Late', $rows[1][8]);
    }

    public function test_excel_export_applies_search_filter(): void
    {
        $user = $this->createUser('user', ['name' => 'Budi Santoso', 'nip' => '1234567890']);
        $other = $this->createUser('user', ['name' => 'Siti Aminah', 'nip' => '9876543210']);
        $this->createAttendance($user);
        $this->createAttendance($other);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(
            route('attendance.recap.export.excel', ['search' => 'budi']),
        );

        $rows = $this->parseXlsx($response);
        $this->assertCount(2, $rows);
        $this->assertSame('Budi Santoso', $rows[1][2]);
        $this->assertSame('1234567890', $rows[1][1]);
    }

    public function test_excel_export_applies_nip_filter(): void
    {
        $user = $this->createUser('user', ['nip' => '1234567890']);
        $other = $this->createUser('user', ['nip' => '9876543210']);
        $this->createAttendance($user);
        $this->createAttendance($other);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(
            route('attendance.recap.export.excel', ['nip' => '2345']),
        );

        $rows = $this->parseXlsx($response);
        $this->assertCount(2, $rows);
        $this->assertSame('1234567890', $rows[1][1]);
    }

    public function test_excel_export_applies_office_filter(): void
    {
        $officeA = $this->createOffice('JKT001', 'Jakarta Office');
        $officeB = $this->createOffice('BDO001', 'Bandung Office');
        $userA = $this->createUser('user', ['office_id' => $officeA->id, 'nip' => '1111111111']);
        $userB = $this->createUser('user', ['office_id' => $officeB->id, 'nip' => '2222222222']);
        $this->createAttendance($userA);
        $this->createAttendance($userB);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(
            route('attendance.recap.export.excel', ['office_id' => $officeB->id]),
        );

        $rows = $this->parseXlsx($response);
        $this->assertCount(2, $rows);
        $this->assertSame('2222222222', $rows[1][1]);
    }

    public function test_excel_export_with_empty_filters_returns_all_visible_data(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user);
        $this->createAttendance($user, [
            'attendance_date' => '2026-08-06',
            'check_in_time' => '08:30:00',
            'attendance_status' => 'late',
        ]);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap.export.excel'));

        $rows = $this->parseXlsx($response);
        $this->assertCount(3, $rows);
    }

    public function test_excel_export_includes_coordinates(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, [
            'latitude' => '-6.1666667',
            'longitude' => '106.8000000',
        ]);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap.export.excel'));

        $rows = $this->parseXlsx($response);
        $this->assertSame(-6.1666667, (float) $rows[1][10]);
        $this->assertSame(106.8, (float) $rows[1][11]);
    }

    public function test_pdf_export_has_correct_filename(): void
    {
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap.export.pdf'));

        $response->assertOk();
        $this->assertStringContainsString(
            'attendance-recap-'.now()->format('Y-m-d').'.pdf',
            $response->headers->get('content-disposition'),
        );
    }

    public function test_export_rejects_invalid_status_filter(): void
    {
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(
            route('attendance.recap.export.excel', ['attendance_status' => 'invalid']),
        );

        $response->assertSessionHasErrors('attendance_status');
    }

    public function test_export_rejects_end_date_before_start_date(): void
    {
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(
            route('attendance.recap.export.excel', [
                'start_date' => '2026-08-10',
                'end_date' => '2026-08-01',
            ]),
        );

        $response->assertSessionHasErrors('end_date');
    }

    public function test_excel_uses_recap_cutoff_not_db_status(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, [
            'check_in_time' => '07:58:00',
            'attendance_status' => 'on_time',
        ]);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap.export.excel'));

        $rows = $this->parseXlsx($response);
        $this->assertSame('Late', $rows[1][8]);
        $this->assertSame(3, (int) $rows[1][9]);
    }

    public function test_excel_includes_late_minutes_column(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, [
            'check_in_time' => '09:00:00',
            'attendance_status' => 'late',
        ]);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap.export.excel'));

        $rows = $this->parseXlsx($response);
        $this->assertSame('Late', $rows[1][8]);
        $this->assertSame(65, (int) $rows[1][9]);
    }

    public function test_excel_shows_zero_late_minutes_for_on_time(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, [
            'check_in_time' => '07:55:00',
            'attendance_status' => 'on_time',
        ]);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap.export.excel'));

        $rows = $this->parseXlsx($response);
        $this->assertSame('On Time', $rows[1][8]);
        $this->assertSame(0, (int) $rows[1][9]);
    }

    public function test_excel_null_check_in_shows_empty_late_minutes(): void
    {
        $user = $this->createUser('user');
        $this->createAttendance($user, [
            'check_in_time' => null,
            'attendance_status' => 'absent',
        ]);
        $admin = $this->createUser('super_admin');

        $response = $this->actingAs($admin)->get(route('attendance.recap.export.excel'));

        $rows = $this->parseXlsx($response);
        $this->assertSame('On Time', $rows[1][8]);
        $this->assertNull($rows[1][9]);
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

    /**
     * @return array<int, array<int, string|float|int|null>>
     */
    private function parseXlsx(TestResponse $response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'recap-export');
        file_put_contents($path, $response->streamedContent());

        try {
            $sheet = IOFactory::load($path)->getActiveSheet();

            return $sheet->toArray();
        } finally {
            @unlink($path);
        }
    }
}

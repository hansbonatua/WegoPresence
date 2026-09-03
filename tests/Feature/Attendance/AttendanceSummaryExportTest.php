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
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class AttendanceSummaryExportTest extends TestCase
{
    use RefreshDatabase;

    private static int $officeSequence = 0;

    public function test_admin_can_export_the_summary(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id, 'nip' => '010001']);
        $this->createAttendance($user, $start);

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
        ]));

        $response->assertOk();

        $sheet = $this->loadSheet($response);

        $this->assertSame('WEGOPRESENCE', $sheet->getCell('A1')->getValue());
        $this->assertSame('Attendance Summary', $sheet->getCell('A2')->getValue());
        $this->assertSame('010001', (string) $sheet->getCell('B7')->getValue());
    }

    public function test_regular_users_are_forbidden(): void
    {
        [$start] = $this->mondayBasedWeek();
        $user = $this->createUser('user');

        $this->actingAs($user)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
        ]))->assertForbidden();
    }

    public function test_super_admins_are_forbidden(): void
    {
        [$start] = $this->mondayBasedWeek();
        $super = $this->createManager('super_admin');

        $this->actingAs($super)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
        ]))->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        [$start] = $this->mondayBasedWeek();

        $this->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
        ]))->assertRedirect(route('login'));
    }

    public function test_filename_uses_the_server_date(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
        ]));

        $expected = 'attendance-summary-'.now()->format('Y-m-d').'.xlsx';

        $response->assertOk();
        $this->assertStringContainsString($expected, $response->headers->get('content-disposition'));
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
    }

    public function test_content_type_is_xlsx(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
        ]));

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type'),
        );
    }

    public function test_the_body_starts_with_xlsx_magic_bytes(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
        ]));

        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_start_date_is_applied_to_the_columns(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $this->createUser('user', ['office_id' => $admin->office_id]);

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(4)->toDateString(),
        ]));

        $sheet = $this->loadSheet($response);

        $firstDateColumn = Coordinate::stringFromColumnIndex(5);

        $this->assertSame($start->format('d M'), $sheet->getCell($firstDateColumn.'6')->getValue());
        $this->assertStringContainsString(
            $start->format('d F Y'),
            $sheet->getCell('A3')->getValue(),
        );
    }

    public function test_end_date_is_applied_to_the_columns(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $this->createUser('user', ['office_id' => $admin->office_id]);

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(4)->toDateString(),
        ]));

        $sheet = $this->loadSheet($response);

        $lastDateColumn = Coordinate::stringFromColumnIndex(9);

        $this->assertSame(
            $start->copy()->addDays(4)->format('d M'),
            $sheet->getCell($lastDateColumn.'6')->getValue(),
        );
        $this->assertStringContainsString(
            $start->copy()->addDays(4)->format('d F Y'),
            $sheet->getCell('A3')->getValue(),
        );
    }

    public function test_ranges_of_up_to_31_days_are_allowed(): void
    {
        $admin = $this->createManager('admin');

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]));

        $response->assertOk();
        $this->assertSame('31 Aug', $this->loadSheet($response)->getCell('AI6')->getValue());
    }

    public function test_ranges_longer_than_31_days_are_rejected(): void
    {
        $admin = $this->createManager('admin');

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => '2026-08-01',
            'end_date' => '2026-09-02',
        ]));

        $response->assertSessionHasErrors('end_date');
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $admin = $this->createManager('admin');

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-01',
        ]));

        $response->assertSessionHasErrors('end_date');
    }

    public function test_missing_dates_are_rejected(): void
    {
        $admin = $this->createManager('admin');

        $this->actingAs($admin)->get(route('attendance.summary.export'))
            ->assertSessionHasErrors(['start_date', 'end_date']);
    }

    public function test_admin_only_exports_users_from_their_own_office(): void
    {
        [$start] = $this->mondayBasedWeek();
        $adminOffice = $this->createOffice();
        $otherOffice = $this->createOffice();
        $admin = $this->createManager('admin', ['office_id' => $adminOffice->id]);
        $own = $this->createUser('user', ['office_id' => $adminOffice->id, 'nip' => '010001']);
        $other = $this->createUser('user', ['office_id' => $otherOffice->id, 'nip' => '010002']);

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
        ]));

        $sheet = $this->loadSheet($response);

        $this->assertSame('1', (string) $sheet->getCell('A7')->getValue());
        $this->assertSame($own->nip, (string) $sheet->getCell('B7')->getValue());
        $this->assertNull($sheet->getCell('A8')->getValue());
        $this->assertStringContainsString($adminOffice->office_name, $sheet->getCell('A4')->getValue());
    }

    public function test_office_id_cannot_bypass_the_admin_scope(): void
    {
        [$start] = $this->mondayBasedWeek();
        $adminOffice = $this->createOffice();
        $otherOffice = $this->createOffice();
        $admin = $this->createManager('admin', ['office_id' => $adminOffice->id]);
        $this->createUser('user', ['office_id' => $adminOffice->id, 'nip' => '010001']);
        $other = $this->createUser('user', ['office_id' => $otherOffice->id, 'nip' => '010002']);

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
            'office_id' => $otherOffice->id,
        ]));

        $sheet = $this->loadSheet($response);

        $this->assertNotSame($other->nip, $sheet->getCell('B7')->getValue());
        $this->assertSame('010001', $sheet->getCell('B7')->getValue());
    }

    public function test_saturday_attendance_is_exported_as_present(): void
    {
        [$start, $saturday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createAttendance($user, $saturday);

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(6)->toDateString(),
        ]));

        $sheet = $this->loadSheet($response);

        $saturdayColumn = Coordinate::stringFromColumnIndex(5 + 5);

        $this->assertSame('H', $sheet->getCell($saturdayColumn.'7')->getValue());
    }

    public function test_saturday_without_attendance_is_exported_as_absent(): void
    {
        [$start, $saturday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $saturday->toDateString(),
            'end_date' => $saturday->toDateString(),
        ]));

        $this->assertSame('A', $this->loadSheet($response)->getCell('E7')->getValue());
    }

    public function test_sunday_without_attendance_is_exported_as_absent(): void
    {
        [$start, , $sunday] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $sunday->toDateString(),
            'end_date' => $sunday->toDateString(),
        ]));

        $this->assertSame('A', $this->loadSheet($response)->getCell('E7')->getValue());
    }

    public function test_attendance_is_exported_as_present(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createAttendance($user, $start);

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
        ]));

        $this->assertSame('H', $this->loadSheet($response)->getCell('E7')->getValue());
    }

    public function test_missing_attendance_is_exported_as_absent(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $this->createUser('user', ['office_id' => $admin->office_id]);

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
        ]));

        $this->assertSame('A', $this->loadSheet($response)->getCell('E7')->getValue());
    }

    public function test_approved_sick_leave_is_exported_as_sick(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id, 'nip' => '010001']);
        $this->createSickLeave($user, $start, 'approved');

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
        ]));

        $this->assertSame('S', $this->loadSheet($response)->getCell('E7')->getValue());
    }

    public function test_approved_leave_is_exported_as_leave(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createLeave($user, $start, 'approved');

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
        ]));

        $this->assertSame('C', $this->loadSheet($response)->getCell('E7')->getValue());
    }

    public function test_approved_permission_is_exported_as_permission(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createPermission($user, $start, 'approved');

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
        ]));

        $this->assertSame('I', $this->loadSheet($response)->getCell('E7')->getValue());
    }

    public function test_approved_dinas_is_exported_as_d(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $user = $this->createUser('user', ['office_id' => $admin->office_id]);
        $this->createBusinessTrip($user, $start, 'approved');

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
        ]));

        $this->assertSame('D', $this->loadSheet($response)->getCell('E7')->getValue());
    }

    public function test_export_includes_the_legend(): void
    {
        [$start] = $this->mondayBasedWeek();
        $admin = $this->createManager('admin');
        $this->createUser('user', ['office_id' => $admin->office_id]);

        $response = $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
        ]));

        $sheet = $this->loadSheet($response);

        $this->assertSame('Legend:', $sheet->getCell('A9')->getValue());
        $this->assertSame('H = Hadir', $sheet->getCell('A10')->getValue());
        $this->assertSame('A = Absen', $sheet->getCell('A11')->getValue());
        $this->assertSame('D = Dinas', $sheet->getCell('A12')->getValue());
        $this->assertSame('S = Sakit', $sheet->getCell('A13')->getValue());
        $this->assertSame('C = Cuti', $sheet->getCell('A14')->getValue());
        $this->assertSame('I = Izin', $sheet->getCell('A15')->getValue());
        $this->assertNull($sheet->getCell('A16')->getValue());
    }

    public function test_export_does_not_suffer_from_n_plus_one_queries(): void
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

        $this->actingAs($admin)->get(route('attendance.summary.export', [
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(6)->toDateString(),
        ]))->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(10, $queryCount);
    }

    private function loadSheet(TestResponse $response): Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'attendance-summary');

        file_put_contents($path, $response->streamedContent());

        $sheet = (new Xlsx)->load($path)->getActiveSheet();

        unlink($path);

        return $sheet;
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
            'attendance_status' => 'present',
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

    private function createBusinessTrip(User $user, CarbonImmutable $date, string $status): BusinessTrip
    {
        return BusinessTrip::query()->create([
            'user_id' => $user->id,
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
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

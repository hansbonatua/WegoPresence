<?php

namespace Tests\Feature\Dashboard;

use App\Models\Attendance;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_cards_count_wit_attendance_across_wib_midnight(): void
    {
        Carbon::setTestNow('2026-01-02 00:10:00', 'Asia/Jayapura');

        $admin = $this->createUser('admin');
        $employee = $this->createUser('user');

        $this->createWitAttendance($employee);

        $data = app(DashboardService::class)->getDashboardData($admin);

        $this->assertSame(1, $this->cardValue($data, 'attendance_today'));
    }

    public function test_dashboard_user_today_attendance_returns_wit_record(): void
    {
        Carbon::setTestNow('2026-01-02 00:10:00', 'Asia/Jayapura');

        $employee = $this->createUser('user');

        $this->createWitAttendance($employee);

        $data = app(DashboardService::class)->getDashboardData($employee);

        $this->assertSame('user', $data['dashboard_variant']);
        $this->assertSame('00:05', $data['today_attendance']['check_in_time']);
        $this->assertSame('present', $data['today_attendance']['attendance_status']);
    }

    private function createWitAttendance(User $user): Attendance
    {
        return Attendance::query()->create([
            'user_id' => $user->id,
            'attendance_date' => '2026-01-02',
            'check_in_time' => '00:05:00',
            'attendance_status' => 'present',
            'attendance_timezone' => 'Asia/Jayapura',
            'latitude' => -2.5337,
            'longitude' => 140.7181,
        ]);
    }

    private function createUser(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(['name' => $role], ['name' => $role]);

        $office = Office::query()->firstOrCreate(
            ['office_code' => 'DSH01'],
            [
                'office_code' => 'DSH01',
                'office_name' => 'Dashboard Test Office',
                'city' => 'Jayapura',
                'address' => 'Jayapura',
                'status' => 'active',
            ],
        );

        return User::factory()->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
            'status' => 'active',
        ]);
    }

    private function cardValue(array $data, string $id): int
    {
        return (int) collect($data['cards'])->firstWhere('id', $id)['value'];
    }
}

<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AttendanceTodayForResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        Gate::before(fn () => true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_today_for_returns_open_wit_record_across_wib_midnight(): void
    {
        Carbon::setTestNow('2026-01-02 00:10:00', 'Asia/Jayapura');

        $user = $this->createUser();

        $this->postCheckIn($user, -2.5337, 140.7181)->assertSessionHas('success');

        $attendance = $user->attendances()->firstOrFail();

        $this->assertSame('2026-01-02', $attendance->attendance_date->format('Y-m-d'));
        $this->assertSame('Asia/Jayapura', $attendance->attendance_timezone);

        $resolved = app(AttendanceService::class)->todayFor($user);

        $this->assertNotNull($resolved);
        $this->assertSame($attendance->id, $resolved->id);

        Carbon::setTestNow('2026-01-02 00:15:00', 'Asia/Jayapura');

        $this->postCheckOut($user)->assertSessionHas('success');

        $this->assertNotNull($attendance->fresh()->check_out_time);
    }

    public function test_today_for_returns_open_wita_record_across_wib_midnight(): void
    {
        Carbon::setTestNow('2026-01-02 00:10:00', 'Asia/Makassar');

        $user = $this->createUser();

        $this->postCheckIn($user, -5.1477, 119.4327)->assertSessionHas('success');

        $attendance = $user->attendances()->firstOrFail();

        $this->assertSame('2026-01-02', $attendance->attendance_date->format('Y-m-d'));
        $this->assertSame('Asia/Makassar', $attendance->attendance_timezone);

        $resolved = app(AttendanceService::class)->todayFor($user);

        $this->assertNotNull($resolved);
        $this->assertSame($attendance->id, $resolved->id);

        Carbon::setTestNow('2026-01-02 00:15:00', 'Asia/Makassar');

        $this->postCheckOut($user)->assertSessionHas('success');

        $this->assertNotNull($attendance->fresh()->check_out_time);
    }

    public function test_today_for_returns_null_for_stale_open_record_older_than_one_day(): void
    {
        $user = $this->createUser();

        $attendance = Attendance::query()->create([
            'user_id' => $user->id,
            'attendance_date' => today()->subDays(2)->format('Y-m-d'),
            'check_in_time' => '08:00:00',
            'attendance_status' => 'present',
            'attendance_timezone' => 'Asia/Jakarta',
            'check_out_time' => null,
            'latitude' => 0,
            'longitude' => 0,
        ]);

        $this->assertNull(app(AttendanceService::class)->todayFor($user));
        $this->assertNull($attendance->fresh()->check_out_time);
    }

    private function createUser(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'user'], ['name' => 'user']);

        $office = Office::query()->firstOrCreate(
            ['office_code' => 'TST02'],
            [
                'office_code' => 'TST02',
                'office_name' => 'TodayFor Test Office',
                'city' => 'Jayapura',
                'address' => 'Jayapura',
                'status' => 'active',
            ],
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'office_id' => $office->id,
            'status' => 'active',
        ]);
    }

    private function postCheckIn(User $user, float $latitude, float $longitude): TestResponse
    {
        return $this->actingAs($user)
            ->withoutMiddleware(EnsureEmailIsVerified::class)
            ->post('/attendance/check-in', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'position_timestamp' => now()->getTimestampMs(),
                'photo' => UploadedFile::fake()->image('photo.jpg'),
            ]);
    }

    private function postCheckOut(User $user): TestResponse
    {
        return $this->actingAs($user)
            ->withoutMiddleware(EnsureEmailIsVerified::class)
            ->post('/attendance/check-out', [
                'photo' => UploadedFile::fake()->image('photo-out.jpg'),
            ]);
    }
}

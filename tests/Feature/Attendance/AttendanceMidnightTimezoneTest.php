<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AttendanceMidnightTimezoneTest extends TestCase
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

    public function test_check_in_duplicate_is_blocked_for_wit_user_around_local_midnight(): void
    {
        Carbon::setTestNow('2026-01-02 00:10:00', 'Asia/Jayapura');

        $user = $this->createUser();

        $this->postCheckIn($user, -2.5337, 140.7181)->assertSessionHas('success');

        $this->postCheckIn($user, -2.5337, 140.7181)->assertSessionHas('error');

        $this->assertSame(
            1,
            Attendance::query()->where('user_id', $user->id)->count(),
        );

        $attendance = Attendance::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('2026-01-02', $attendance->attendance_date->format('Y-m-d'));
        $this->assertSame('Asia/Jayapura', $attendance->attendance_timezone);
    }

    public function test_check_in_duplicate_is_blocked_for_wita_user_around_local_midnight(): void
    {
        Carbon::setTestNow('2026-01-02 00:10:00', 'Asia/Makassar');

        $user = $this->createUser();

        $this->postCheckIn($user, -5.1477, 119.4327)->assertSessionHas('success');

        $this->postCheckIn($user, -5.1477, 119.4327)->assertSessionHas('error');

        $this->assertSame(
            1,
            Attendance::query()->where('user_id', $user->id)->count(),
        );

        $attendance = Attendance::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('2026-01-02', $attendance->attendance_date->format('Y-m-d'));
        $this->assertSame('Asia/Makassar', $attendance->attendance_timezone);
    }

    private function createUser(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'user'], ['name' => 'user']);

        $office = Office::query()->firstOrCreate(
            ['office_code' => 'TST01'],
            [
                'office_code' => 'TST01',
                'office_name' => 'Midnight Test Office',
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
}

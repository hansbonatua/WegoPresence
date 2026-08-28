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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AttendanceTimezoneTest extends TestCase
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
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    public function test_application_timezone_is_asia_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
    }

    public function test_check_in_at_14_46_wib_is_stored_as_wib_wall_clock_and_marked_late(): void
    {
        $user = $this->createUser($this->createOffice('DKI Jakarta'));
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        Carbon::setTestNow('2026-08-07 14:46:00');

        $this->postCheckIn()->assertSessionHas('success');

        $attendance = Attendance::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('2026-08-07', $attendance->attendance_date->format('Y-m-d'));
        $this->assertSame('14:46:00', $attendance->check_in_time->format('H:i:s'));
        $this->assertSame('late', $attendance->attendance_status);
    }

    public function test_check_in_at_07_46_wib_is_stored_as_wib_wall_clock_and_marked_on_time(): void
    {
        $user = $this->createUser($this->createOffice('DKI Jakarta'));
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        Carbon::setTestNow('2026-08-07 07:46:00');

        $this->postCheckIn()->assertSessionHas('success');

        $attendance = Attendance::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('2026-08-07', $attendance->attendance_date->format('Y-m-d'));
        $this->assertSame('07:46:00', $attendance->check_in_time->format('H:i:s'));
        $this->assertSame('on_time', $attendance->attendance_status);
    }

    public function test_check_out_at_17_10_wib_is_stored_as_wib_wall_clock(): void
    {
        $user = $this->createUser($this->createOffice('DKI Jakarta'));
        Http::preventStrayRequests();

        $attendance = Attendance::query()->create([
            'user_id' => $user->id,
            'attendance_date' => '2026-08-07',
            'check_in_time' => '08:00:00',
            'attendance_status' => 'late',
        ]);

        Carbon::setTestNow('2026-08-07 17:10:00');

        $this->actingAs($user)
            ->withoutMiddleware(EnsureEmailIsVerified::class)
            ->post('/attendance/check-out', [
                'photo' => UploadedFile::fake()->image('photo.jpg'),
            ])->assertSessionHas('success', 'Checked out successfully.');

        $this->assertSame('17:10:00', $attendance->fresh()->check_out_time->format('H:i:s'));
    }

    public function test_midnight_boundary_00_30_wib_uses_the_next_calendar_day(): void
    {
        $user = $this->createUser($this->createOffice('DKI Jakarta'));
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        Carbon::setTestNow('2026-08-08 00:30:00');

        $this->postCheckIn()->assertSessionHas('success');

        $attendance = Attendance::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('2026-08-08', $attendance->attendance_date->format('Y-m-d'));
        $this->assertSame('00:30:00', $attendance->check_in_time->format('H:i:s'));
    }

    public function test_23_30_wib_stays_on_the_same_calendar_day(): void
    {
        $user = $this->createUser($this->createOffice('DKI Jakarta'));
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        Carbon::setTestNow('2026-08-07 23:30:00');

        $this->postCheckIn()->assertSessionHas('success');

        $attendance = Attendance::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('2026-08-07', $attendance->attendance_date->format('Y-m-d'));
    }

    public function test_wib_midnight_boundary_does_not_create_duplicate_attendance(): void
    {
        $user = $this->createUser($this->createOffice('DKI Jakarta'));
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        Carbon::setTestNow('2026-08-07 23:59:00');
        $this->postCheckIn()->assertSessionHas('success');

        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');
        Carbon::setTestNow('2026-08-07 12:00:00');
        $this->postCheckIn($this->freshPayload())->assertSessionHas('error', 'You have already checked in today.');

        $this->assertSame(1, Attendance::query()->where('user_id', $user->id)->count());
    }

    public function test_gps_position_freshness_is_preserved_in_asia_jakarta_timezone(): void
    {
        $this->createUser($this->createOffice('DKI Jakarta'));
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        Carbon::setTestNow('2026-08-07 14:46:00');

        $this->postCheckIn([
            'position_timestamp' => Carbon::now()->getTimestampMs() - 300_000,
        ])->assertSessionHas('error', 'Your location data is stale. Please refresh your location and try again.');

        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        $this->postCheckIn([
            'position_timestamp' => Carbon::now()->getTimestampMs() - 20_000,
        ])->assertSessionHas('success');

        $this->assertSame(1, Attendance::query()->count());
    }

    private function freshPayload(): array
    {
        return [
            'latitude' => -6.1666667,
            'longitude' => 106.8,
            'position_timestamp' => Carbon::now()->getTimestampMs(),
            'photo' => UploadedFile::fake()->image('photo.jpg'),
        ];
    }

    private static int $officeSequence = 0;

    private static int $userSequence = 0;

    private function createOffice(string $city, string $startTime = '08:00:00'): Office
    {
        self::$officeSequence++;

        return Office::query()->create([
            'office_code' => 'TZ'.str_pad((string) self::$officeSequence, 3, '0', STR_PAD_LEFT),
            'office_name' => 'Jakarta Head Office',
            'city' => $city,
            'address' => 'Wisma 67, Jl. Tanah Abang II No. 67, Jakarta Pusat 10160',
            'status' => 'active',
            'start_time' => $startTime,
            'end_time' => '17:00:00',
        ]);
    }

    private function createUser(Office $office): User
    {
        self::$userSequence++;

        $role = Role::query()->firstOrCreate(['name' => 'user']);

        return User::query()->create([
            'role_id' => $role->id,
            'office_id' => $office->id,
            'nip' => '88890'.(self::$userSequence % 10),
            'name' => 'Timezone Test User '.self::$userSequence,
            'position' => 'Staff',
            'email' => 'tz.test'.self::$userSequence.'@example.com',
            'join_date' => '2026-01-01',
            'city' => 'Bandar Lampung',
            'status' => 'active',
            'password' => 'password',
        ]);
    }

    private function fakeNominatim(string $city): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'lat' => '-6.1666667',
                'lon' => '106.8000000',
                'address' => [
                    'city' => $city,
                    'county' => $city,
                ],
            ], 200),
        ]);
    }

    private function postCheckIn(array $payload = []): TestResponse
    {
        $user = User::query()->firstOrFail();

        return $this->actingAs($user)
            ->withoutMiddleware(EnsureEmailIsVerified::class)
            ->post('/attendance/check-in', array_merge($this->freshPayload(), $payload));
    }
}

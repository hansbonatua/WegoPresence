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

class AttendanceGpsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-07 07:50:00');

        Storage::fake('public');

        Gate::before(fn () => true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    public function test_check_in_is_allowed_when_gps_city_matches_office_city(): void
    {
        $user = $this->createUser($this->createOffice('DKI Jakarta'));
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        $response = $this->postCheckIn();

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $attendance = Attendance::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($attendance);
        $this->assertSame('on_time', $attendance->attendance_status);
        $this->assertSame('2026-08-07', $attendance->attendance_date->format('Y-m-d'));
        $this->assertSame('-6.1666667', (string) $attendance->latitude);
        $this->assertSame('106.8000000', (string) $attendance->longitude);
    }

    public function test_check_in_is_accepted_from_any_administrative_area_of_dki_jakarta(): void
    {
        foreach ([
            'Jakarta Pusat',
            'Jakarta Timur',
            'Jakarta Selatan',
            'Jakarta Barat',
            'Jakarta Utara',
            'Kepulauan Seribu',
        ] as $area) {
            $user = $this->createUser($this->createOffice('DKI Jakarta'));
            $this->fakeNominatim($area);

            $response = $this->postCheckIn([], $user);

            $response->assertRedirect();
            $response->assertSessionHas('success');

            $this->assertDatabaseHas('attendances', [
                'user_id' => $user->id,
                'attendance_status' => 'on_time',
            ]);
        }
    }

    public function test_check_in_is_accepted_from_english_names_of_dki_jakarta(): void
    {
        foreach ([
            'Central Jakarta',
            'East Jakarta',
            'South Jakarta',
            'West Jakarta',
            'North Jakarta',
            'Thousand Islands',
        ] as $area) {
            $user = $this->createUser($this->createOffice('DKI Jakarta'));
            $this->fakeNominatim($area);

            $response = $this->postCheckIn([], $user);

            $response->assertRedirect();
            $response->assertSessionHas('success');

            $this->assertDatabaseHas('attendances', [
                'user_id' => $user->id,
                'attendance_status' => 'on_time',
            ]);
        }
    }

    public function test_check_in_is_rejected_from_areas_outside_dki_jakarta(): void
    {
        foreach ([
            'Bekasi',
            'Depok',
            'Tangerang',
            'Tangerang Selatan',
            'Bogor',
        ] as $area) {
            $user = $this->createUser($this->createOffice('DKI Jakarta'));
            $this->fakeNominatim($area);

            $response = $this->postCheckIn([], $user);

            $response->assertRedirect();
            $response->assertSessionHas('error', 'Your current location is outside your assigned office city.');

            $this->assertDatabaseMissing('attendances', ['user_id' => $user->id]);
        }
    }

    public function test_check_in_is_rejected_when_gps_city_differs_from_office_city(): void
    {
        $this->createUser($this->createOffice('DKI Jakarta'));
        $this->fakeNominatim('Bandar Lampung');

        $response = $this->postCheckIn();

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Your current location is outside your assigned office city.');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_check_in_is_rejected_when_reverse_geocoding_fails(): void
    {
        $this->createUser($this->createOffice('DKI Jakarta'));
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response('Server Error', 500),
        ]);

        $response = $this->postCheckIn();

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Unable to verify your location. Please make sure GPS is enabled and try again.');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_check_in_is_rejected_when_office_is_not_configured(): void
    {
        $user = $this->createUser($this->createOffice('DKI Jakarta'));
        $user->office->delete();
        $this->fakeNominatim('Jakarta Pusat');

        $response = $this->postCheckIn();

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Your office location is not configured.');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_check_in_is_rejected_when_user_already_checked_in_today(): void
    {
        $user = $this->createUser($this->createOffice('DKI Jakarta'));

        Attendance::query()->create([
            'user_id' => $user->id,
            'attendance_date' => '2026-08-07',
            'check_in_time' => '07:00:00',
            'attendance_status' => 'on_time',
        ]);

        $this->fakeNominatim('Jakarta Pusat');

        $response = $this->postCheckIn();

        $response->assertRedirect();
        $response->assertSessionHas('error', 'You have already checked in today.');
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_check_in_is_rejected_when_latitude_is_invalid(): void
    {
        $this->createUser($this->createOffice('DKI Jakarta'));

        $response = $this->postCheckIn(['latitude' => 91, 'longitude' => 106.8]);

        $response->assertSessionHasErrors('latitude');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_check_in_is_rejected_when_longitude_is_invalid(): void
    {
        $this->createUser($this->createOffice('DKI Jakarta'));

        $response = $this->postCheckIn(['latitude' => -6.1666667, 'longitude' => 181]);

        $response->assertSessionHasErrors('longitude');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_check_in_is_rejected_when_latitude_is_missing(): void
    {
        $this->createUser($this->createOffice('DKI Jakarta'));

        $response = $this->postCheckIn(['latitude' => null, 'longitude' => 106.8]);

        $response->assertSessionHasErrors('latitude');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_check_out_does_not_require_gps(): void
    {
        $user = $this->createUser($this->createOffice('DKI Jakarta'));
        Http::preventStrayRequests();

        $attendance = Attendance::query()->create([
            'user_id' => $user->id,
            'attendance_date' => '2026-08-07',
            'check_in_time' => '07:50:00',
            'attendance_status' => 'on_time',
        ]);

        $response = $this->actingAs($user)
            ->withoutMiddleware(EnsureEmailIsVerified::class)
            ->post('/attendance/check-out', [
                'photo' => UploadedFile::fake()->image('photo.jpg'),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Checked out successfully.');
        $this->assertNotNull($attendance->fresh()->check_out_time);
    }

    public function test_check_out_without_check_in_is_rejected(): void
    {
        $user = $this->createUser($this->createOffice('DKI Jakarta'));

        $response = $this->actingAs($user)
            ->withoutMiddleware(EnsureEmailIsVerified::class)
            ->post('/attendance/check-out', [
                'photo' => UploadedFile::fake()->image('photo.jpg'),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'You have not checked in today.');
    }

    public function test_check_in_is_rejected_when_position_timestamp_is_stale(): void
    {
        $this->createUser($this->createOffice('DKI Jakarta'));
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        $response = $this->postCheckIn([
            'latitude' => -6.1666667,
            'longitude' => 106.8,
            'position_timestamp' => now()->getTimestampMs() - 300_000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Your location data is stale. Please refresh your location and try again.');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_check_in_is_rejected_when_position_timestamp_is_in_the_future(): void
    {
        $this->createUser($this->createOffice('DKI Jakarta'));
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        $response = $this->postCheckIn([
            'latitude' => -6.1666667,
            'longitude' => 106.8,
            'position_timestamp' => now()->getTimestampMs() + 300_000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Your location data is stale. Please refresh your location and try again.');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_check_in_is_rejected_when_position_timestamp_is_missing(): void
    {
        $this->createUser($this->createOffice('DKI Jakarta'));

        $response = $this->postCheckIn([
            'latitude' => -6.1666667,
            'longitude' => 106.8,
            'position_timestamp' => null,
        ]);

        $response->assertSessionHasErrors('position_timestamp');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_check_in_is_rejected_when_position_timestamp_is_not_numeric(): void
    {
        $this->createUser($this->createOffice('DKI Jakarta'));

        $response = $this->postCheckIn([
            'latitude' => -6.1666667,
            'longitude' => 106.8,
            'position_timestamp' => 'not-a-timestamp',
        ]);

        $response->assertSessionHasErrors('position_timestamp');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_check_in_accepts_fresh_position_within_tolerance(): void
    {
        $user = $this->createUser($this->createOffice('DKI Jakarta'));
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        $response = $this->postCheckIn([
            'latitude' => -6.1666667,
            'longitude' => 106.8,
            'position_timestamp' => now()->getTimestampMs() - 20_000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('attendances', ['user_id' => $user->id]);
    }

    public function test_check_in_accepts_position_timestamp_at_the_tolerance_boundaries(): void
    {
        $firstUser = $this->createUser($this->createOffice('DKI Jakarta'));
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        $response = $this->postCheckIn([
            'latitude' => -6.1666667,
            'longitude' => 106.8,
            'position_timestamp' => now()->getTimestampMs() - 30_000,
        ], $firstUser);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $secondUser = $this->createUser($this->createOffice('DKI Jakarta', '08:00:00', 'JKT002'), '889901', 'gps.test2@example.com');
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        $response = $this->postCheckIn([
            'latitude' => -6.1666667,
            'longitude' => 106.8,
            'position_timestamp' => now()->getTimestampMs() + 30_000,
        ], $secondUser);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('attendances', 2);
    }

    public function test_position_timestamp_is_not_persisted(): void
    {
        $this->createUser($this->createOffice('DKI Jakarta'));
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        $this->postCheckIn()->assertSessionHas('success');

        $attendance = Attendance::query()->firstOrFail();

        $this->assertArrayNotHasKey('position_timestamp', $attendance->getAttributes());
    }

    public function test_check_in_is_allowed_on_saturday(): void
    {
        $this->createUser($this->createOffice('DKI Jakarta'));
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        $saturday = Carbon::parse('this saturday');
        $this->travelTo($saturday->setTime(9, 0));

        $this->postCheckIn()->assertSessionHas('success');
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_check_in_is_allowed_on_sunday(): void
    {
        $this->createUser($this->createOffice('DKI Jakarta'));
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        $sunday = Carbon::parse('this sunday');
        $this->travelTo($sunday->setTime(9, 0));

        $this->postCheckIn()->assertSessionHas('success');
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_check_out_is_allowed_on_saturday(): void
    {
        $user = $this->createUser($this->createOffice('DKI Jakarta'));

        $saturday = Carbon::parse('this saturday')->toDateString();
        $attendance = Attendance::query()->create([
            'user_id' => $user->id,
            'attendance_date' => $saturday,
            'check_in_time' => '09:00:00',
            'attendance_status' => 'on_time',
        ]);

        $this->travelTo(Carbon::parse($saturday)->setTime(17, 0));

        $this->actingAs($user)
            ->withoutMiddleware(EnsureEmailIsVerified::class)
            ->post('/attendance/check-out', [
                'photo' => UploadedFile::fake()->image('photo-out.jpg'),
            ])->assertSessionHas('success', 'Checked out successfully.');

        $this->assertNotNull($attendance->fresh()->check_out_time);
    }

    public function test_check_out_is_allowed_on_sunday(): void
    {
        $user = $this->createUser($this->createOffice('DKI Jakarta'));

        $sunday = Carbon::parse('this sunday')->toDateString();
        $attendance = Attendance::query()->create([
            'user_id' => $user->id,
            'attendance_date' => $sunday,
            'check_in_time' => '09:00:00',
            'attendance_status' => 'on_time',
        ]);

        $this->travelTo(Carbon::parse($sunday)->setTime(17, 0));

        $this->actingAs($user)
            ->withoutMiddleware(EnsureEmailIsVerified::class)
            ->post('/attendance/check-out', [
                'photo' => UploadedFile::fake()->image('photo-out.jpg'),
            ])->assertSessionHas('success', 'Checked out successfully.');

        $this->assertNotNull($attendance->fresh()->check_out_time);
    }

    private static int $officeSequence = 0;

    private static int $userSequence = 0;

    private function createOffice(string $city, string $startTime = '08:00:00', ?string $officeCode = null): Office
    {
        self::$officeSequence++;

        return Office::query()->create([
            'office_code' => $officeCode ?? 'JKT'.str_pad((string) self::$officeSequence, 3, '0', STR_PAD_LEFT),
            'office_name' => 'Jakarta Head Office',
            'city' => $city,
            'address' => 'Wisma 67, Jl. Tanah Abang II No. 67, Jakarta Pusat 10160',
            'status' => 'active',
            'start_time' => $startTime,
            'end_time' => '17:00:00',
        ]);
    }

    private function createUser(Office $office, ?string $nip = null, ?string $email = null): User
    {
        self::$userSequence++;

        $nip ??= '88990'.(self::$userSequence % 10);
        $email ??= 'gps.test'.self::$userSequence.'@example.com';

        $role = Role::query()->firstOrCreate(['name' => 'user']);

        return User::query()->create([
            'role_id' => $role->id,
            'office_id' => $office->id,
            'nip' => $nip,
            'name' => 'GPS Test User '.self::$userSequence,
            'position' => 'Staff',
            'email' => $email,
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

    private function postCheckIn(array $payload = [], ?User $user = null): TestResponse
    {
        $user ??= User::query()->firstOrFail();

        return $this->actingAs($user)
            ->withoutMiddleware(EnsureEmailIsVerified::class)
            ->post('/attendance/check-in', array_merge([
                'latitude' => -6.1666667,
                'longitude' => 106.8,
                'position_timestamp' => now()->getTimestampMs(),
                'photo' => UploadedFile::fake()->image('photo.jpg'),
            ], $payload));
    }
}

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
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AttendanceTimezonePerLocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        self::$nominatimResponses = [];
        self::$nominatimStubRegistered = false;

        Carbon::setTestNow('2026-08-07 07:50:00');

        Storage::fake('public');

        Gate::before(fn () => true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    public function test_java_gps_locations_store_asia_jakarta_timezone(): void
    {
        $user = $this->createUser($this->createOffice('DKI Jakarta'), 'DKI Jakarta');
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat', -6.1666667, 106.8);

        $response = $this->postCheckIn([
            'latitude' => -6.1666667,
            'longitude' => 106.8,
        ], $user);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'attendance_timezone' => 'Asia/Jakarta',
        ]);
    }

    public function test_sulawesi_gps_locations_store_asia_makassar_timezone(): void
    {
        $user = $this->createUser($this->createOffice('Makassar'), 'Makassar');
        $this->fakeNominatim('Makassar', -5.1477, 119.4327);

        $response = $this->postCheckIn([
            'latitude' => -5.1477,
            'longitude' => 119.4327,
        ], $user);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'attendance_timezone' => 'Asia/Makassar',
        ]);
    }

    public function test_papua_gps_locations_store_asia_jayapura_timezone(): void
    {
        $user = $this->createUser($this->createOffice('Jayapura'), 'Jayapura');
        $this->fakeNominatim('Jayapura', -2.5367, 140.7173);

        $response = $this->postCheckIn([
            'latitude' => -2.5367,
            'longitude' => 140.7173,
        ], $user);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'attendance_timezone' => 'Asia/Jayapura',
        ]);
    }

    public function test_late_calculation_uses_the_resolved_local_timezone(): void
    {
        Carbon::setTestNow('2026-08-07 08:00:00');

        $cases = [
            ['city' => 'Jakarta', 'cityGps' => 'Kota Administrasi Jakarta Pusat', 'lat' => -6.1666667, 'lng' => 106.8, 'tz' => 'Asia/Jakarta', 'status' => 'present'],
            ['city' => 'Makassar', 'cityGps' => 'Makassar', 'lat' => -5.1477, 'lng' => 119.4327, 'tz' => 'Asia/Makassar', 'status' => 'late'],
            ['city' => 'Jayapura', 'cityGps' => 'Jayapura', 'lat' => -2.5367, 'lng' => 140.7173, 'tz' => 'Asia/Jayapura', 'status' => 'late'],
        ];

        foreach ($cases as $case) {
            $user = $this->createUser($this->createOffice($case['city']), $case['city']);
            $this->fakeNominatim($case['cityGps'], $case['lat'], $case['lng']);

            $response = $this->postCheckIn([
                'latitude' => $case['lat'],
                'longitude' => $case['lng'],
            ], $user);

            $response->assertSessionHas('success');
            $this->assertDatabaseHas('attendances', [
                'user_id' => $user->id,
                'attendance_status' => $case['status'],
                'attendance_timezone' => $case['tz'],
            ]);
        }
    }

    public function test_attendance_date_uses_the_resolved_local_timezone(): void
    {
        Carbon::setTestNow('2026-08-07 23:00:00');

        $jakartaUser = $this->createUser($this->createOffice('DKI Jakarta'), 'DKI Jakarta');
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat', -6.1666667, 106.8);

        $this->postCheckIn([
            'latitude' => -6.1666667,
            'longitude' => 106.8,
        ], $jakartaUser)->assertSessionHas('success');

        $jakartaAttendance = Attendance::query()->where('user_id', $jakartaUser->id)->firstOrFail();

        $this->assertSame('2026-08-07', $jakartaAttendance->attendance_date->format('Y-m-d'));
        $this->assertSame('Asia/Jakarta', $jakartaAttendance->attendance_timezone);

        $jayapuraUser = $this->createUser($this->createOffice('Jayapura'), 'Jayapura');
        $this->fakeNominatim('Jayapura', -2.5367, 140.7173);

        $this->postCheckIn([
            'latitude' => -2.5367,
            'longitude' => 140.7173,
        ], $jayapuraUser)->assertSessionHas('success');

        $jayapuraAttendance = Attendance::query()->where('user_id', $jayapuraUser->id)->firstOrFail();

        $this->assertSame('2026-08-08', $jayapuraAttendance->attendance_date->format('Y-m-d'));
        $this->assertSame('Asia/Jayapura', $jayapuraAttendance->attendance_timezone);
    }

    public function test_check_out_uses_the_stored_check_in_timezone(): void
    {
        Carbon::setTestNow('2026-08-07 07:50:00');

        $jakartaUser = $this->createUser($this->createOffice('DKI Jakarta'), 'DKI Jakarta');
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat', -6.1666667, 106.8);

        $this->postCheckIn([
            'latitude' => -6.1666667,
            'longitude' => 106.8,
        ], $jakartaUser)->assertSessionHas('success');

        $jayapuraUser = $this->createUser($this->createOffice('Jayapura'), 'Jayapura');
        $this->fakeNominatim('Jayapura', -2.5367, 140.7173);

        $this->postCheckIn([
            'latitude' => -2.5367,
            'longitude' => 140.7173,
        ], $jayapuraUser)->assertSessionHas('success');

        Carbon::setTestNow('2026-08-07 23:00:00');

        $this->actingAs($jakartaUser)
            ->withoutMiddleware(EnsureEmailIsVerified::class)
            ->post('/attendance/check-out', [
                'photo' => UploadedFile::fake()->image('photo-jkt.jpg'),
            ])->assertSessionHas('success', 'Checked out successfully.');

        $this->actingAs($jayapuraUser)
            ->withoutMiddleware(EnsureEmailIsVerified::class)
            ->post('/attendance/check-out', [
                'photo' => UploadedFile::fake()->image('photo-jpr.jpg'),
            ])->assertSessionHas('success', 'Checked out successfully.');

        $this->assertSame(
            '23:00:00',
            Attendance::query()->where('user_id', $jakartaUser->id)->first()->check_out_time?->format('H:i:s'),
        );

        $this->assertSame(
            '01:00:00',
            Attendance::query()->where('user_id', $jayapuraUser->id)->first()->check_out_time?->format('H:i:s'),
        );
    }

    public function test_legacy_record_with_a_backfilled_timezone_still_checks_out(): void
    {
        $user = $this->createUser($this->createOffice('DKI Jakarta'), 'DKI Jakarta');

        Attendance::query()->create([
            'user_id' => $user->id,
            'attendance_date' => '2026-08-07',
            'check_in_time' => '08:00:00',
            'attendance_status' => 'present',
            'attendance_timezone' => 'Asia/Jakarta',
        ]);

        Carbon::setTestNow('2026-08-07 23:00:00');

        $this->actingAs($user)
            ->withoutMiddleware(EnsureEmailIsVerified::class)
            ->post('/attendance/check-out', [
                'photo' => UploadedFile::fake()->image('photo-legacy.jpg'),
            ])->assertSessionHas('success', 'Checked out successfully.');

        $this->assertSame(
            '23:00:00',
            Attendance::query()->where('user_id', $user->id)->first()->check_out_time?->format('H:i:s'),
        );
    }

    public function test_timezone_for_falls_back_to_the_application_timezone(): void
    {
        $this->assertSame(
            config('app.timezone'),
            AttendanceService::timezoneFor(null),
        );

        $this->assertSame(
            'Asia/Makassar',
            AttendanceService::timezoneFor('Asia/Makassar'),
        );
    }

    private static int $officeSequence = 0;

    private static int $userSequence = 0;

    private function createOffice(string $city): Office
    {
        self::$officeSequence++;

        return Office::query()->create([
            'office_code' => 'TZ'.str_pad((string) self::$officeSequence, 3, '0', STR_PAD_LEFT),
            'office_name' => $city.' Office',
            'city' => $city,
            'address' => $city,
            'status' => 'active',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
    }

    private function createUser(Office $office, string $city): User
    {
        self::$userSequence++;

        $role = Role::query()->firstOrCreate(['name' => 'user']);

        return User::query()->create([
            'role_id' => $role->id,
            'office_id' => $office->id,
            'nip' => '78780'.(self::$userSequence % 10),
            'name' => 'Timezone User '.self::$userSequence,
            'position' => 'Staff',
            'email' => 'loc.test'.self::$userSequence.'@example.com',
            'join_date' => '2026-01-01',
            'city' => $city,
            'status' => 'active',
            'password' => 'password',
        ]);
    }

    /**
     * Coordinates served by the fake Nominatim endpoint, keyed by
     * "lat,lon". Http::fake() merges stubs instead of replacing them,
     * so a single dispatch stub is registered once and answers every
     * check-in location.
     *
     * @var array<string, array{city: string, lat: float, lon: float}>
     */
    private static array $nominatimResponses = [];

    private static bool $nominatimStubRegistered = false;

    private function fakeNominatim(string $city, float $latitude, float $longitude): void
    {
        self::$nominatimResponses[$latitude.','.$longitude] = [
            'city' => $city,
            'lat' => $latitude,
            'lon' => $longitude,
        ];

        if (self::$nominatimStubRegistered) {
            return;
        }

        self::$nominatimStubRegistered = true;

        Http::fake(function (Request $request) {
            parse_str((string) Str::after($request->url(), '?'), $query);

            foreach (self::$nominatimResponses as $response) {
                if ((string) $response['lat'] === ($query['lat'] ?? null)
                    && (string) $response['lon'] === ($query['lon'] ?? null)) {
                    return Http::response([
                        'lat' => (string) $response['lat'],
                        'lon' => (string) $response['lon'],
                        'address' => [
                            'city' => $response['city'],
                            'county' => $response['city'],
                        ],
                    ], 200);
                }
            }

            return Http::response(['error' => 'Not found'], 404);
        });
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

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

class AttendanceCityNormalizationTest extends TestCase
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

    public function test_bandung_user_check_in_from_bandung_succeeds(): void
    {
        $user = $this->createUser($this->createOffice('Bandung'), 'Bandung');
        $this->fakeNominatim('Bandung');

        $response = $this->postCheckIn([
            'latitude' => -6.9175,
            'longitude' => 107.6191,
        ], $user);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'attendance_status' => 'present',
            'attendance_timezone' => 'Asia/Jakarta',
        ]);

        $attendance = Attendance::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('-6.9175000', (string) $attendance->latitude);
        $this->assertSame('107.6191000', (string) $attendance->longitude);
    }

    public function test_bandung_user_check_in_from_jakarta_succeeds(): void
    {
        $user = $this->createUser($this->createOffice('Bandung'), 'Bandung');
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        $response = $this->postCheckIn([
            'latitude' => -6.1666667,
            'longitude' => 106.8,
        ], $user);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'attendance_status' => 'present',
            'attendance_timezone' => 'Asia/Jakarta',
        ]);
    }

    public function test_bandung_user_check_in_from_makassar_succeeds(): void
    {
        $user = $this->createUser($this->createOffice('Bandung'), 'Bandung');
        $this->fakeNominatim('Makassar');

        $response = $this->postCheckIn([
            'latitude' => -5.1477,
            'longitude' => 119.4327,
        ], $user);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'attendance_status' => 'late',
            'attendance_timezone' => 'Asia/Makassar',
        ]);
    }

    public function test_bandung_user_check_in_from_jayapura_succeeds(): void
    {
        $user = $this->createUser($this->createOffice('Bandung'), 'Bandung');
        $this->fakeNominatim('Jayapura');

        $response = $this->postCheckIn([
            'latitude' => -2.5367,
            'longitude' => 140.7173,
        ], $user);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'attendance_status' => 'late',
            'attendance_timezone' => 'Asia/Jayapura',
        ]);
    }

    public function test_check_in_succeeds_when_office_differs_from_gps_location(): void
    {
        $user = $this->createUser($this->createOffice('Makassar'), 'Bandung');
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        $response = $this->postCheckIn([
            'latitude' => -6.1666667,
            'longitude' => 106.8,
        ], $user);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
        ]);
    }

    public function test_check_in_succeeds_even_when_geocoding_is_unavailable(): void
    {
        $user = $this->createUser($this->createOffice('Bandung'), 'Bandung');
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response('Server Error', 500),
        ]);

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

    public function test_maros_user_check_in_from_kabupaten_maros_succeeds(): void
    {
        $user = $this->createUser($this->createOffice('Maros'), 'Maros');
        $this->fakeNominatim('Kabupaten Maros');

        $response = $this->postCheckIn([
            'latitude' => -5.0109,
            'longitude' => 119.5764,
        ], $user);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
        ]);
    }

    public function test_manado_user_with_makassar_office_checks_in_from_manado(): void
    {
        $user = $this->createUser($this->createOffice('Makassar'), 'Manado');
        $this->fakeNominatim('Manado');

        $response = $this->postCheckIn([
            'latitude' => 1.4748,
            'longitude' => 124.8421,
        ], $user);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
        ]);
    }

    private static int $officeSequence = 0;

    private static int $userSequence = 0;

    private function createOffice(string $city): Office
    {
        self::$officeSequence++;

        return Office::query()->create([
            'office_code' => 'CN'.str_pad((string) self::$officeSequence, 3, '0', STR_PAD_LEFT),
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
            'nip' => '99440'.(self::$userSequence % 10),
            'name' => 'City Normalization User '.self::$userSequence,
            'position' => 'Staff',
            'email' => 'cn.test'.self::$userSequence.'@example.com',
            'join_date' => '2026-01-01',
            'city' => $city,
            'status' => 'active',
            'password' => 'password',
        ]);
    }

    private function fakeNominatim(string $city): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'lat' => '-5.0109',
                'lon' => '119.5764',
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
                'latitude' => -5.0109,
                'longitude' => 119.5764,
                'position_timestamp' => now()->getTimestampMs(),
                'photo' => UploadedFile::fake()->image('photo.jpg'),
            ], $payload));
    }
}

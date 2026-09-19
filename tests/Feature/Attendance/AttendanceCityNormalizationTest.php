<?php

namespace Tests\Feature\Attendance;

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

    public function test_maros_user_check_in_from_another_city_is_rejected(): void
    {
        $user = $this->createUser($this->createOffice('Maros'), 'Maros');
        $this->fakeNominatim('Surabaya');

        $response = $this->postCheckIn([
            'latitude' => -7.2575,
            'longitude' => 112.7521,
        ], $user);

        $response->assertSessionHas('error', 'Your current location is outside your assigned working city.');
        $this->assertDatabaseMissing('attendances', [
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

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

class AttendancePhotoTest extends TestCase
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

    public function test_check_in_with_photo_succeeds(): void
    {
        $user = $this->createUser($this->createOffice('Jakarta Pusat'));
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        $response = $this->postCheckIn();

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $attendance = Attendance::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertNotNull($attendance->check_in_photo);
        $this->assertStringStartsWith('attendance/check-in/', $attendance->check_in_photo);
        $this->assertTrue(Storage::disk('public')->exists($attendance->check_in_photo));
    }

    public function test_check_out_with_photo_succeeds(): void
    {
        $user = $this->createUser($this->createOffice('Jakarta Pusat'));

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

        $attendance = $attendance->fresh();

        $this->assertNotNull($attendance->check_out_photo);
        $this->assertStringStartsWith('attendance/check-out/', $attendance->check_out_photo);
        $this->assertTrue(Storage::disk('public')->exists($attendance->check_out_photo));
    }

    public function test_check_in_without_photo_is_rejected(): void
    {
        $this->createUser($this->createOffice('Jakarta Pusat'));

        $response = $this->postCheckIn([
            'photo' => null,
        ]);

        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_check_out_without_photo_is_rejected(): void
    {
        $user = $this->createUser($this->createOffice('Jakarta Pusat'));

        Attendance::query()->create([
            'user_id' => $user->id,
            'attendance_date' => '2026-08-07',
            'check_in_time' => '07:50:00',
            'attendance_status' => 'on_time',
        ]);

        $response = $this->actingAs($user)
            ->withoutMiddleware(EnsureEmailIsVerified::class)
            ->post('/attendance/check-out');

        $response->assertSessionHasErrors('photo');
    }

    public function test_check_in_rejects_a_pdf_file(): void
    {
        $this->createUser($this->createOffice('Jakarta Pusat'));

        $response = $this->postCheckIn([
            'photo' => UploadedFile::fake()->create('photo.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_check_in_rejects_a_non_image_file(): void
    {
        $this->createUser($this->createOffice('Jakarta Pusat'));

        $response = $this->postCheckIn([
            'photo' => UploadedFile::fake()->create('photo.txt', 100, 'text/plain'),
        ]);

        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_check_in_rejects_a_photo_larger_than_two_megabytes(): void
    {
        $this->createUser($this->createOffice('Jakarta Pusat'));

        $response = $this->postCheckIn([
            'photo' => UploadedFile::fake()->image('photo.jpg')->size(2049),
        ]);

        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_check_in_still_requires_gps_fields(): void
    {
        $this->createUser($this->createOffice('Jakarta Pusat'));

        $response = $this->postCheckIn([
            'latitude' => null,
            'longitude' => null,
            'position_timestamp' => null,
        ]);

        $response->assertSessionHasErrors(['latitude', 'longitude', 'position_timestamp']);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_check_out_does_not_require_gps(): void
    {
        $user = $this->createUser($this->createOffice('Jakarta Pusat'));
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

    public function test_duplicate_check_in_is_still_rejected_with_a_valid_photo(): void
    {
        $user = $this->createUser($this->createOffice('Jakarta Pusat'));

        Attendance::query()->create([
            'user_id' => $user->id,
            'attendance_date' => '2026-08-07',
            'check_in_time' => '07:00:00',
            'attendance_status' => 'on_time',
        ]);

        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        $response = $this->postCheckIn();

        $response->assertRedirect();
        $response->assertSessionHas('error', 'You have already checked in today.');
        $this->assertDatabaseCount('attendances', 1);
        $this->assertEmpty(Storage::disk('public')->files('attendance/check-in'));
    }

    public function test_duplicate_check_out_is_still_rejected_with_a_valid_photo(): void
    {
        $user = $this->createUser($this->createOffice('Jakarta Pusat'));

        $attendance = Attendance::query()->create([
            'user_id' => $user->id,
            'attendance_date' => '2026-08-07',
            'check_in_time' => '07:50:00',
            'check_out_time' => '16:00:00',
            'attendance_status' => 'on_time',
        ]);

        $response = $this->actingAs($user)
            ->withoutMiddleware(EnsureEmailIsVerified::class)
            ->post('/attendance/check-out', [
                'photo' => UploadedFile::fake()->image('photo.jpg'),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'You have already checked out today.');
        $this->assertSame('16:00:00', $attendance->fresh()->check_out_time?->format('H:i:s'));
        $this->assertEmpty(Storage::disk('public')->files('attendance/check-out'));
    }

    public function test_stale_position_is_still_rejected_with_a_valid_photo(): void
    {
        $this->createUser($this->createOffice('Jakarta Pusat'));
        $this->fakeNominatim('Kota Administrasi Jakarta Pusat');

        $response = $this->postCheckIn([
            'position_timestamp' => now()->getTimestampMs() - 300_000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Your location data is stale. Please refresh your location and try again.');
        $this->assertDatabaseCount('attendances', 0);
        $this->assertEmpty(Storage::disk('public')->files('attendance/check-in'));
    }

    public function test_city_mismatch_is_still_rejected_with_a_valid_photo(): void
    {
        $this->createUser($this->createOffice('Jakarta Pusat'));
        $this->fakeNominatim('Bandar Lampung');

        $response = $this->postCheckIn();

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Your current location is outside your assigned office city.');
        $this->assertDatabaseCount('attendances', 0);
        $this->assertEmpty(Storage::disk('public')->files('attendance/check-in'));
    }

    public function test_missing_office_is_still_rejected_with_a_valid_photo(): void
    {
        $user = $this->createUser($this->createOffice('Jakarta Pusat'));
        $user->office->delete();
        $this->fakeNominatim('Jakarta Pusat');

        $response = $this->postCheckIn();

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Your office location is not configured.');
        $this->assertDatabaseCount('attendances', 0);
        $this->assertEmpty(Storage::disk('public')->files('attendance/check-in'));
    }

    private function createOffice(string $city, string $startTime = '08:00:00', string $officeCode = 'JKT001'): Office
    {
        return Office::query()->create([
            'office_code' => $officeCode,
            'office_name' => 'Jakarta Head Office',
            'city' => $city,
            'address' => 'Wisma 67, Jl. Tanah Abang II No. 67, Jakarta Pusat 10160',
            'status' => 'active',
            'start_time' => $startTime,
            'end_time' => '17:00:00',
        ]);
    }

    private function createUser(Office $office, string $nip = '889900', string $email = 'gps.test@example.com'): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'user']);

        return User::query()->create([
            'role_id' => $role->id,
            'office_id' => $office->id,
            'nip' => $nip,
            'name' => 'GPS Test User',
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

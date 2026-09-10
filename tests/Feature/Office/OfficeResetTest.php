<?php

namespace Tests\Feature\Office;

use App\Models\Attendance;
use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\OfficeResetService;
use Database\Seeders\OfficeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_seeder_creates_only_five_official_offices(): void
    {
        $this->seed(OfficeSeeder::class);
        $this->seed(OfficeSeeder::class);

        $this->assertSame(5, Office::query()->count());

        $this->assertEqualsCanonicalizing(
            ['JKT001', 'MDN001', 'SBY001', 'MKS001', 'PDG001'],
            Office::query()->pluck('office_code')->all(),
        );
    }

    public function test_reset_leaves_exactly_the_five_official_offices(): void
    {
        $this->createOffice('BDG001', 'Bandung');
        $this->createOffice('MLG001', 'Malang');
        $trashed = $this->createOffice('OFC999', 'Ambon');
        $trashed->delete();

        $report = app(OfficeResetService::class)->reset();

        $this->assertSame(5, Office::query()->count());
        $this->assertSame(5, Office::withTrashed()->count());

        $this->assertDatabaseHas('offices', [
            'office_code' => 'JKT001',
            'office_name' => 'Jakarta Head Office',
            'city' => 'Jakarta',
        ]);
        $this->assertDatabaseHas('offices', ['office_code' => 'MDN001', 'office_name' => 'Medan Office', 'city' => 'Medan']);
        $this->assertDatabaseHas('offices', ['office_code' => 'SBY001', 'office_name' => 'Surabaya Office', 'city' => 'Surabaya']);
        $this->assertDatabaseHas('offices', ['office_code' => 'MKS001', 'office_name' => 'Makassar Office', 'city' => 'Makassar']);
        $this->assertDatabaseHas('offices', ['office_code' => 'PDG001', 'office_name' => 'Padang Office', 'city' => 'Padang']);

        $this->assertContains('BDG001', $report['deleted_offices']);
        $this->assertContains('OFC999', $report['deleted_offices']);
    }

    public function test_user_from_legacy_office_is_moved_to_jakarta(): void
    {
        $bandung = $this->createOffice('BDG001', 'Bandung');
        $denpasar = $this->createOffice('DPS001', 'Denpasar');
        $bandungUser = $this->createRoleUser('user', ['office_id' => $bandung->id, 'city' => 'Bandung']);
        $denpasarUser = $this->createRoleUser('user', ['office_id' => $denpasar->id, 'city' => 'Denpasar']);

        $report = app(OfficeResetService::class)->reset();

        $this->assertSame('JKT001', $bandungUser->fresh()->office->office_code);
        $this->assertSame('JKT001', $denpasarUser->fresh()->office->office_code);
        $this->assertSame(2, $report['users_moved_to_jakarta']);
        $this->assertSame(2, User::query()->count());
        $this->assertSame(5, Office::query()->count());
    }

    public function test_attendance_history_is_preserved_after_reset(): void
    {
        $bandung = $this->createOffice('BDG001', 'Bandung');
        $user = $this->createRoleUser('user', ['office_id' => $bandung->id]);
        $attendance = Attendance::query()->create([
            'user_id' => $user->id,
            'attendance_date' => today(),
            'attendance_status' => 'present',
            'check_in_time' => '08:05:00',
            'check_out_time' => '17:00:00',
        ]);

        app(OfficeResetService::class)->reset();

        $this->assertSame('JKT001', $user->fresh()->office->office_code);
        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_permission_stays_connected_after_reset(): void
    {
        $bandung = $this->createOffice('BDG001', 'Bandung');
        $user = $this->createRoleUser('user', ['office_id' => $bandung->id]);
        $permission = Permission::query()->create([
            'user_id' => $user->id,
            'type' => 'personal',
            'start_date' => today()->format('Y-m-d'),
            'reason' => 'Family event',
            'status' => 'pending',
        ]);

        app(OfficeResetService::class)->reset();

        $this->assertSame('JKT001', $user->fresh()->office->office_code);
        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_admin_can_still_see_all_data_after_reset(): void
    {
        $adminOffice = Office::query()->where('office_code', 'JKT001')->firstOrFail();
        $bandung = $this->createOffice('BDG001', 'Bandung');
        $admin = $this->createRoleUser('admin', ['office_id' => $adminOffice->id, 'nip' => '600001']);
        $other = $this->createRoleUser('user', ['office_id' => $bandung->id, 'nip' => '600002']);

        app(OfficeResetService::class)->reset();

        $this->assertSame('JKT001', $other->fresh()->office->office_code);

        $response = $this->actingAs($admin)->get(route('users.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('users/index')
                ->where('users.data', fn ($data) => collect($data)->pluck('id')->contains($other->id)));
    }

    public function test_super_admin_remains_normal_after_reset(): void
    {
        $superOffice = Office::query()->where('office_code', 'JKT001')->firstOrFail();
        $bandung = $this->createOffice('BDG001', 'Bandung');
        $super = $this->createRoleUser('super_admin', ['office_id' => $superOffice->id, 'nip' => '600003']);
        $other = $this->createRoleUser('user', ['office_id' => $bandung->id, 'nip' => '600004']);

        app(OfficeResetService::class)->reset();

        $this->assertSame('JKT001', $other->fresh()->office->office_code);

        $response = $this->actingAs($super)->get(route('users.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('users/index')
                ->where('users.data', fn ($data) => collect($data)->pluck('id')->contains($other->id)));
    }

    public function test_reset_is_idempotent(): void
    {
        $bandung = $this->createOffice('BDG001', 'Bandung');
        $this->createRoleUser('user', ['office_id' => $bandung->id]);

        $service = app(OfficeResetService::class);
        $service->reset();
        $service->reset();

        $this->assertSame(5, Office::query()->count());
        $this->assertSame(5, Office::withTrashed()->count());
    }

    private function createOffice(string $code, string $city): Office
    {
        return Office::query()->create([
            'office_code' => $code,
            'office_name' => 'Office '.$city,
            'city' => $city,
            'address' => $city,
            'status' => 'active',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createRoleUser(string $roleName, array $attributes = []): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['name' => $roleName]);

        return User::factory()->create(['role_id' => $role->id, ...$attributes]);
    }
}

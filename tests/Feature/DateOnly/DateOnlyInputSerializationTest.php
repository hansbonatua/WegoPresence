<?php

namespace Tests\Feature\DateOnly;

use App\Models\BusinessTrip;
use App\Models\LeaveRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SickLeave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateOnlyInputSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_start_date_serializes_without_timezone_shift(): void
    {
        $user = $this->createUser('user');
        $permission = Permission::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-09-10',
            'reason' => 'Medical checkup',
            'status' => 'approved',
        ]);

        $this->assertSame('2026-09-10', $permission->start_date->format('Y-m-d'));
        $this->assertSame('2026-09-10', $permission->toArray()['start_date']);
        $this->assertSame('2026-09-10', json_decode($permission->toJson(), true)['start_date']);
        $this->assertStringNotContainsString('T', (string) $permission->toArray()['start_date']);
    }

    public function test_leave_dates_serialize_without_timezone_shift(): void
    {
        $user = $this->createUser('user');
        $leave = LeaveRequest::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-11',
            'reason' => 'Family visit',
            'status' => 'pending',
        ]);

        $this->assertSame('2026-09-10', $leave->start_date->format('Y-m-d'));
        $this->assertSame('2026-09-11', $leave->end_date->format('Y-m-d'));

        $array = $leave->toArray();
        $this->assertSame('2026-09-10', $array['start_date']);
        $this->assertSame('2026-09-11', $array['end_date']);

        $json = json_decode($leave->toJson(), true);
        $this->assertSame('2026-09-10', $json['start_date']);
        $this->assertSame('2026-09-11', $json['end_date']);
        $this->assertStringNotContainsString('T', (string) $json['start_date']);
        $this->assertStringNotContainsString('T', (string) $json['end_date']);
    }

    public function test_sick_leave_dates_serialize_without_timezone_shift(): void
    {
        $user = $this->createUser('user');
        $sickLeave = SickLeave::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-11',
            'reason' => 'Flu',
            'status' => 'approved',
        ]);

        $this->assertSame('2026-09-10', $sickLeave->start_date->format('Y-m-d'));
        $this->assertSame('2026-09-11', $sickLeave->end_date->format('Y-m-d'));

        $array = $sickLeave->toArray();
        $this->assertSame('2026-09-10', $array['start_date']);
        $this->assertSame('2026-09-11', $array['end_date']);

        $json = json_decode($sickLeave->toJson(), true);
        $this->assertSame('2026-09-10', $json['start_date']);
        $this->assertSame('2026-09-11', $json['end_date']);
        $this->assertStringNotContainsString('T', (string) $json['start_date']);
        $this->assertStringNotContainsString('T', (string) $json['end_date']);
    }

    public function test_business_trip_dates_serialize_without_timezone_shift(): void
    {
        $user = $this->createUser('user');
        $trip = BusinessTrip::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'destination' => 'Bandung',
            'purpose' => 'Meeting',
            'status' => 'approved',
        ]);

        $this->assertSame('2026-09-10', $trip->start_date->format('Y-m-d'));
        $this->assertSame('2026-09-12', $trip->end_date->format('Y-m-d'));

        $array = $trip->toArray();
        $this->assertSame('2026-09-10', $array['start_date']);
        $this->assertSame('2026-09-12', $array['end_date']);

        $json = json_decode($trip->toJson(), true);
        $this->assertSame('2026-09-10', $json['start_date']);
        $this->assertSame('2026-09-12', $json['end_date']);
        $this->assertStringNotContainsString('T', (string) $json['start_date']);
        $this->assertStringNotContainsString('T', (string) $json['end_date']);
    }

    public function test_leave_dates_are_serialized_without_timezone_shift_in_the_listing(): void
    {
        $user = $this->createUser('user');
        $leave = LeaveRequest::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-11',
            'reason' => 'Family visit',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->get(route('leaves.index'));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('leaves/index')
            ->where('leaves.data', function ($data) use ($leave): bool {
                $row = collect($data)->firstWhere('id', $leave->id);

                return $row !== null
                    && $row['start_date'] === '2026-09-10'
                    && $row['end_date'] === '2026-09-11';
            }));
    }

    public function test_permission_start_date_is_serialized_without_timezone_shift_in_the_listing(): void
    {
        $user = $this->createUser('user');
        $permission = Permission::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-09-10',
            'reason' => 'Medical checkup',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)->get(route('permissions.index'));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('permissions/index')
            ->where('permissions.data', function ($data) use ($permission): bool {
                $row = collect($data)->firstWhere('id', $permission->id);

                return $row !== null && $row['start_date'] === '2026-09-10';
            }));
    }

    private function createUser(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['name' => $roleName]);

        return User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
    }
}

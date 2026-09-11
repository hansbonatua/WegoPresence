<?php

namespace Tests\Feature\User;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JoinDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_join_date_stays_a_date_only_string_in_model_serialization(): void
    {
        $user = User::factory()->create(['join_date' => '2026-09-10']);

        $this->assertSame('2026-09-10', $user->join_date);
        $this->assertSame('2026-09-10', $user->toArray()['join_date']);
        $this->assertSame('2026-09-10', json_decode($user->toJson(), true)['join_date']);
    }

    public function test_join_date_is_preserved_by_registration(): void
    {
        $office = Office::query()->where('office_code', 'JKT001')->firstOrFail();

        $this->post(route('register.store'), [
            'office_id' => $office->id,
            'nip' => '123456',
            'name' => 'Join Date User',
            'position' => 'Staff',
            'email' => 'join.date@example.com',
            'phone' => '08123456789',
            'join_date' => '2026-09-10',
            'city' => 'Jakarta',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'join.date@example.com',
            'join_date' => '2026-09-10',
        ]);

        $this->assertSame(
            '2026-09-10',
            User::query()->where('email', 'join.date@example.com')->value('join_date'),
        );
    }

    public function test_join_date_is_preserved_when_admin_creates_a_user(): void
    {
        $office = Office::query()->where('office_code', 'JKT001')->firstOrFail();
        $admin = $this->createManager('admin', $office);

        $this->actingAs($admin)->post(route('users.store'), [
            'role_id' => Role::query()->firstOrCreate(['name' => 'user'], ['name' => 'user'])->id,
            'office_id' => $office->id,
            'nip' => '700001',
            'name' => 'Stored User',
            'position' => 'Staff',
            'email' => 'stored.user@example.com',
            'phone' => '08129876543',
            'join_date' => '2026-09-10',
            'city' => 'Jakarta',
            'status' => 'active',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('users.index', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'stored.user@example.com',
            'join_date' => '2026-09-10',
        ]);
    }

    public function test_join_date_is_serialized_without_timezone_shift_in_the_listing(): void
    {
        $office = Office::query()->where('office_code', 'JKT001')->firstOrFail();
        $admin = $this->createManager('admin', $office);
        $user = User::factory()->create([
            'office_id' => $office->id,
            'nip' => '700002',
            'name' => 'Listed User',
            'join_date' => '2026-09-10',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get(route('users.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('users/index')
                ->where('users.data', function ($data) use ($user) {
                    $row = collect($data)->firstWhere('id', $user->id);

                    return $row !== null && $row['join_date'] === '2026-09-10';
                }));
    }

    public function test_join_date_is_serialized_without_timezone_shift_in_the_detail_page(): void
    {
        $office = Office::query()->where('office_code', 'JKT001')->firstOrFail();
        $admin = $this->createManager('admin', $office);
        $user = User::factory()->create([
            'office_id' => $office->id,
            'nip' => '700003',
            'name' => 'Detailed User',
            'join_date' => '2026-09-10',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get(route('users.show', $user));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('users/show')
                ->where('user.join_date', '2026-09-10'));
    }

    private function createManager(string $roleName, Office $office): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['name' => $roleName]);

        return User::factory()->create([
            'role_id' => $role->id,
            'office_id' => $office->id,
            'nip' => $roleName === 'super_admin' ? '600001' : '600002',
            'status' => 'active',
        ]);
    }
}

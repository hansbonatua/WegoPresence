<?php

namespace Tests\Feature\Auth;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private static int $officeSequence = 0;

    /**
     * An office with a unique code.
     */
    private function createOffice(): Office
    {
        self::$officeSequence++;

        return Office::query()->create([
            'office_code' => 'JKT'.str_pad((string) self::$officeSequence, 3, '0', STR_PAD_LEFT),
            'office_name' => 'Office '.self::$officeSequence,
            'city' => 'Jakarta',
            'address' => 'Jl. Test '.self::$officeSequence,
            'status' => 'active',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        static $nipSequence = 90000;

        $nipSequence++;

        return [
            'nip' => (string) $nipSequence,
            'office_id' => $overrides['office_id'] ?? $this->createOffice()->id,
            'name' => $overrides['name'] ?? 'New Employee',
            'position' => $overrides['position'] ?? 'Staff',
            'email' => $overrides['email'] ?? fake()->unique()->safeEmail(),
            'phone' => $overrides['phone'] ?? '08123456789',
            'join_date' => $overrides['join_date'] ?? '2026-08-01',
            'city' => $overrides['city'] ?? 'Jakarta',
            'password' => $overrides['password'] ?? 'password123',
            'password_confirmation' => $overrides['password_confirmation'] ?? 'password123',
            ...array_diff_key($overrides, array_flip([
                'office_id', 'name', 'position', 'email', 'phone',
                'join_date', 'city', 'password', 'password_confirmation',
            ])),
        ];
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_authenticated_users_are_redirected_away_from_registration(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('register'));

        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_a_registration_creates_a_pending_user_with_user_supplied_nip(): void
    {
        $office = $this->createOffice();

        $response = $this->post(route('register.store'), $this->validPayload([
            'office_id' => $office->id,
            'nip' => '123456',
            'name' => 'New Employee',
            'email' => 'new.employee@example.com',
        ]));

        $response->assertRedirect(route('login', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'new.employee@example.com',
            'name' => 'New Employee',
            'office_id' => $office->id,
            'status' => 'pending',
            'nip' => '123456',
            'approved_by' => null,
            'approved_at' => null,
            'rejected_reason' => null,
        ]);

        $user = User::query()->where('email', 'new.employee@example.com')->firstOrFail();

        $this->assertSame('user', $user->role?->name);
        $this->assertSame('pending', $user->status);
        $this->assertSame('123456', $user->nip);
        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(password_verify('password123', $user->password));
    }

    public function test_registration_ignores_spoofed_role_and_status(): void
    {
        $office = $this->createOffice();
        $adminRole = Role::query()->create(['name' => 'admin']);

        $this->post(route('register.store'), $this->validPayload([
            'office_id' => $office->id,
            'nip' => '010999',
            'name' => 'Spoofer',
            'email' => 'spoofer@example.com',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]))->assertRedirect(route('login', absolute: false));

        $user = User::query()->where('email', 'spoofer@example.com')->firstOrFail();

        $this->assertSame('user', $user->role?->name);
        $this->assertSame('pending', $user->status);
        $this->assertSame('010999', $user->nip);
    }

    public function test_registration_requires_nip(): void
    {
        $office = $this->createOffice();

        $response = $this->post(route('register.store'), $this->validPayload([
            'office_id' => $office->id,
            'nip' => '',
        ]));

        $response->assertSessionHasErrors('nip');
    }

    public function test_registration_requires_all_fields(): void
    {
        $response = $this->post(route('register.store'), []);

        $response->assertSessionHasErrors([
            'office_id',
            'nip',
            'name',
            'position',
            'email',
            'phone',
            'join_date',
            'city',
            'password',
        ]);
    }

    public function test_registration_rejects_duplicate_nip_of_active_user(): void
    {
        $existing = User::factory()->create(['status' => 'active']);

        $response = $this->post(route('register.store'), $this->validPayload([
            'nip' => $existing->nip,
            'email' => 'different@example.com',
        ]));

        $response->assertSessionHasErrors('nip');
    }

    public function test_registration_rejects_duplicate_nip_of_pending_user(): void
    {
        $office = $this->createOffice();
        $existing = User::query()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => 'user'], ['name' => 'user'])->id,
            'office_id' => $office->id,
            'nip' => '555555',
            'name' => 'Existing Pending',
            'position' => 'Staff',
            'email' => 'existing.pending@example.com',
            'join_date' => '2026-08-01',
            'city' => 'Jakarta',
            'password' => 'password123',
            'status' => 'pending',
        ]);

        $response = $this->post(route('register.store'), $this->validPayload([
            'nip' => '555555',
            'email' => 'another@example.com',
        ]));

        $response->assertSessionHasErrors('nip');
    }

    public function test_registration_rejects_duplicate_nip_of_rejected_user(): void
    {
        $office = $this->createOffice();
        $existing = User::query()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => 'user'], ['name' => 'user'])->id,
            'office_id' => $office->id,
            'nip' => '666666',
            'name' => 'Rejected User',
            'position' => 'Staff',
            'email' => 'rejected@example.com',
            'join_date' => '2026-08-01',
            'city' => 'Jakarta',
            'password' => 'password123',
            'status' => 'rejected',
            'rejected_reason' => 'Missing docs',
        ]);

        $response = $this->post(route('register.store'), $this->validPayload([
            'nip' => '666666',
            'email' => 'another.rejected@example.com',
        ]));

        $response->assertSessionHasErrors('nip');
    }

    public function test_registration_rejects_duplicate_nip_of_soft_deleted_user(): void
    {
        $office = $this->createOffice();
        $deleted = User::query()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => 'user'], ['name' => 'user'])->id,
            'office_id' => $office->id,
            'nip' => '777777',
            'name' => 'Deleted User',
            'position' => 'Staff',
            'email' => 'deleted@example.com',
            'join_date' => '2026-08-01',
            'city' => 'Jakarta',
            'password' => 'password123',
            'status' => 'active',
        ]);
        $deleted->delete();

        $response = $this->post(route('register.store'), $this->validPayload([
            'nip' => '777777',
            'email' => 'after.delete@example.com',
        ]));

        $response->assertSessionHasErrors('nip');
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $existing = User::factory()->create();

        $response = $this->post(route('register.store'), $this->validPayload([
            'email' => $existing->email,
        ]));

        $response->assertSessionHasErrors('email');
    }

    public function test_registration_rejects_unknown_office(): void
    {
        $response = $this->post(route('register.store'), $this->validPayload([
            'office_id' => 99999,
        ]));

        $response->assertSessionHasErrors('office_id');
    }

    public function test_registration_rejects_inactive_offices(): void
    {
        $inactive = $this->createOffice();
        $inactive->update(['status' => 'inactive']);

        $response = $this->post(route('register.store'), $this->validPayload([
            'office_id' => $inactive->id,
            'email' => 'inactive.office@example.com',
        ]));

        $response->assertSessionHasErrors('office_id');
        $this->assertDatabaseMissing('users', ['email' => 'inactive.office@example.com']);
    }

    public function test_registration_screen_only_lists_active_offices(): void
    {
        $active = $this->createOffice();
        $inactive = $this->createOffice();
        $inactive->update(['status' => 'inactive']);

        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/register')
                ->has('offices', 1)
                ->where('offices.0.id', $active->id));
    }

    public function test_registration_requires_confirmed_password_with_minimum_length(): void
    {
        $office = $this->createOffice();

        $tooShort = $this->post(route('register.store'), $this->validPayload([
            'office_id' => $office->id,
            'email' => 'weak@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]));

        $tooShort->assertSessionHasErrors('password');

        $mismatch = $this->post(route('register.store'), $this->validPayload([
            'office_id' => $office->id,
            'email' => 'mismatch@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ]));

        $mismatch->assertSessionHasErrors('password');
    }

    public function test_pending_users_can_not_sign_in_and_see_a_specific_message(): void
    {
        $user = User::factory()->create(['status' => 'pending', 'nip' => '990001']);

        $response = $this->post(route('login.store'), [
            'nip' => $user->nip,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('nip');

        $this->assertGuest();
    }

    public function test_pending_user_with_nip_can_not_sign_in(): void
    {
        $office = $this->createOffice();

        $pending = User::query()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => 'user'], ['name' => 'user'])->id,
            'office_id' => $office->id,
            'nip' => '123456',
            'name' => 'Has NIP',
            'position' => 'Staff',
            'email' => 'has.nip@example.com',
            'join_date' => '2026-08-01',
            'city' => 'Jakarta',
            'password' => 'password123',
            'status' => 'pending',
        ]);

        $response = $this->post(route('login.store'), [
            'nip' => '123456',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('nip');

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $pending->id, 'status' => 'pending']);
    }

    public function test_pending_user_without_nip_can_not_sign_in(): void
    {
        $office = $this->createOffice();

        $pending = User::query()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => 'user'], ['name' => 'user'])->id,
            'office_id' => $office->id,
            'nip' => null,
            'name' => 'Awaiting NIP',
            'position' => 'Staff',
            'email' => 'awaiting.nip@example.com',
            'join_date' => '2026-08-01',
            'city' => 'Jakarta',
            'password' => 'password123',
            'status' => 'pending',
        ]);

        $response = $this->post(route('login.store'), [
            'nip' => '0011',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('nip');

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $pending->id, 'status' => 'pending']);
    }

    public function test_rejected_users_can_not_sign_in(): void
    {
        $user = User::factory()->create([
            'status' => 'rejected',
            'rejected_reason' => 'Missing documents',
        ]);

        $response = $this->post(route('login.store'), [
            'nip' => $user->nip,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('nip');

        $this->assertGuest();
    }

    public function test_active_users_can_still_sign_in_after_registration_feature(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->post(route('login.store'), [
            'nip' => $user->nip,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_unknown_nip_still_gets_the_generic_credentials_error(): void
    {
        $response = $this->post(route('login.store'), [
            'nip' => 'never-exists',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('nip');
        $this->assertGuest();
    }
}

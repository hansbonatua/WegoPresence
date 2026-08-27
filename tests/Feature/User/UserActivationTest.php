<?php

namespace Tests\Feature\User;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserActivationTest extends TestCase
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

    public function test_admin_can_activate_a_pending_registration_from_their_own_office(): void
    {
        $office = $this->createOffice();
        $admin = $this->createManager('admin', $office);
        $pending = $this->createPending($office, nip: '555555');

        $response = $this->actingAs($admin)->post(route('users.activate', $pending));

        $response->assertRedirect();

        $pending->refresh();

        $this->assertSame('active', $pending->status);
        $this->assertSame('555555', $pending->nip);
        $this->assertSame($admin->id, $pending->approved_by);
        $this->assertNotNull($pending->approved_at);
        $this->assertNull($pending->rejected_reason);
    }

    public function test_super_admin_can_activate_a_pending_registration_from_any_office(): void
    {
        $office = $this->createOffice();
        $super = $this->createManager('super_admin', $office);
        $pending = $this->createPending($office, nip: '888888');

        $this->actingAs($super)->post(route('users.activate', $pending));

        $pending->refresh();
        $this->assertSame('active', $pending->status);
        $this->assertSame('888888', $pending->nip);
    }

    public function test_admin_can_not_activate_a_pending_registration_from_another_office(): void
    {
        $adminOffice = $this->createOffice();
        $otherOffice = $this->createOffice();
        $admin = $this->createManager('admin', $adminOffice);
        $pending = $this->createPending($otherOffice, nip: '555555');

        $response = $this->actingAs($admin)->post(route('users.activate', $pending));

        $response->assertForbidden();
        $this->assertSame('pending', $pending->refresh()->status);
    }

    public function test_regular_users_can_not_activate_or_reject_registrations(): void
    {
        $user = User::factory()->create();
        $pending = $this->createPending($user->office, nip: '555555');

        $activate = $this->actingAs($user)->post(route('users.activate', $pending));
        $reject = $this->actingAs($user)->post(route('users.reject', $pending), [
            'rejected_reason' => 'Nope',
        ]);

        $activate->assertForbidden();
        $reject->assertForbidden();
        $this->assertSame('pending', $pending->refresh()->status);
    }

    public function test_regular_users_can_not_view_the_user_listing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('users.index'));

        $response->assertForbidden();
    }

    public function test_activating_an_already_active_user_is_blocked_without_changing_the_nip(): void
    {
        $office = $this->createOffice();
        $super = $this->createManager('super_admin', $office);
        $active = User::factory()->create(['office_id' => $office->id, 'status' => 'active']);

        $response = $this->actingAs($super)->post(route('users.activate', $active));

        $response->assertRedirect();
        $this->assertSame('active', $active->refresh()->status);
        $this->assertSame($active->nip, $active->nip);
    }

    public function test_rejecting_a_pending_registration_requires_a_reason(): void
    {
        $office = $this->createOffice();
        $admin = $this->createManager('admin', $office);
        $pending = $this->createPending($office, nip: '555555');

        $response = $this->actingAs($admin)->post(route('users.reject', $pending), []);

        $response->assertSessionHasErrors('rejected_reason');
        $this->assertSame('pending', $pending->refresh()->status);
    }

    public function test_admin_can_reject_a_pending_registration_with_a_reason(): void
    {
        $office = $this->createOffice();
        $admin = $this->createManager('admin', $office);
        $pending = $this->createPending($office, nip: '555555');

        $response = $this->actingAs($admin)->post(route('users.reject', $pending), [
            'rejected_reason' => 'Missing required documents',
        ]);

        $response->assertRedirect();

        $pending->refresh();

        $this->assertSame('rejected', $pending->status);
        $this->assertSame('Missing required documents', $pending->rejected_reason);
        $this->assertSame($admin->id, $pending->approved_by);
        $this->assertNotNull($pending->approved_at);
        $this->assertNull($pending->nip);
    }

    public function test_rejecting_an_already_rejected_user_is_blocked(): void
    {
        $office = $this->createOffice();
        $super = $this->createManager('super_admin', $office);
        $rejected = $this->createPending($office, nip: '555555');
        $rejected->update(['status' => 'rejected', 'rejected_reason' => 'First reason']);

        $this->actingAs($super)->post(route('users.reject', $rejected), [
            'rejected_reason' => 'Second reason',
        ]);

        $this->assertSame('First reason', $rejected->refresh()->rejected_reason);
    }

    public function test_nip_generation_uses_the_smallest_free_number(): void
    {
        $office = $this->createOffice();
        $super = $this->createManager('super_admin', $office);

        User::query()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => 'user'], ['name' => 'user'])->id,
            'office_id' => $office->id,
            'nip' => '010001',
            'name' => 'Taken One',
            'position' => 'Staff',
            'email' => 'taken.one@example.com',
            'join_date' => '2026-01-01',
            'city' => 'Jakarta',
            'status' => 'active',
            'password' => 'password123',
        ]);

        $first = $this->createPending($office, nip: null);
        $this->actingAs($super)->post(route('users.activate', $first));
        $this->assertSame('010002', $first->refresh()->nip);

        $second = $this->createPending($office, nip: null);
        $this->actingAs($super)->post(route('users.activate', $second));
        $this->assertSame('010003', $second->refresh()->nip);
    }

    public function test_nip_generation_fills_gaps_in_an_existing_sequence(): void
    {
        $office = $this->createOffice();
        $super = $this->createManager('super_admin', $office);

        foreach (['010001', '010003', '010004'] as $nip) {
            User::query()->create([
                'role_id' => Role::query()->firstOrCreate(['name' => 'user'], ['name' => 'user'])->id,
                'office_id' => $office->id,
                'nip' => $nip,
                'name' => "Taken {$nip}",
                'position' => 'Staff',
                'email' => "taken.{$nip}@example.com",
                'join_date' => '2026-01-01',
                'city' => 'Jakarta',
                'status' => 'active',
                'password' => 'password123',
            ]);
        }

        $pending = $this->createPending($office, nip: null);

        $this->actingAs($super)->post(route('users.activate', $pending));

        $this->assertSame('010002', $pending->refresh()->nip);
    }

    public function test_nip_generation_ignores_unrelated_nip_formats(): void
    {
        $office = $this->createOffice();
        $super = $this->createManager('super_admin', $office);

        User::query()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => 'user'], ['name' => 'user'])->id,
            'office_id' => $office->id,
            'nip' => '990001',
            'name' => 'Legacy NIP',
            'position' => 'Staff',
            'email' => 'legacy@example.com',
            'join_date' => '2026-01-01',
            'city' => 'Jakarta',
            'status' => 'active',
            'password' => 'password123',
        ]);

        $pending = $this->createPending($office, nip: null);

        $this->actingAs($super)->post(route('users.activate', $pending));

        $this->assertSame('010001', $pending->refresh()->nip);
    }

    public function test_nip_generation_does_not_reuse_nips_of_soft_deleted_users(): void
    {
        $office = $this->createOffice();
        $super = $this->createManager('super_admin', $office);

        $deleted = User::factory()->create(['office_id' => $office->id, 'nip' => '010001']);
        $deleted->delete();

        $pending = $this->createPending($office, nip: null);

        $this->actingAs($super)->post(route('users.activate', $pending));

        $this->assertSame('010002', $pending->refresh()->nip);
    }

    public function test_concurrent_activations_never_assign_duplicate_nips(): void
    {
        $office = $this->createOffice();
        $super = $this->createManager('super_admin', $office);

        $pendings = collect(range(1, 6))->map(fn () => $this->createPending($office, nip: null));

        $pendings->each(function (User $pending) use ($super) {
            $this->actingAs($super)->post(route('users.activate', $pending));
        });

        $nips = $pendings->map(fn (User $pending) => $pending->refresh()->nip)->all();

        $this->assertCount(6, $nips);
        $this->assertSame(6, count(array_unique($nips)));

        foreach ($nips as $nip) {
            $this->assertMatchesRegularExpression('/^010\d{3}$/', $nip);
        }
    }

    public function test_activation_preserves_user_supplied_nip(): void
    {
        $office = $this->createOffice();
        $super = $this->createManager('super_admin', $office);
        $pending = $this->createPending($office, nip: '123456');

        $this->actingAs($super)->post(route('users.activate', $pending));

        $pending->refresh();
        $this->assertSame('active', $pending->status);
        $this->assertSame('123456', $pending->nip);
    }

    public function test_activation_does_not_generate_nip_when_user_already_has_one(): void
    {
        $office = $this->createOffice();
        $super = $this->createManager('super_admin', $office);

        User::query()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => 'user'], ['name' => 'user'])->id,
            'office_id' => $office->id,
            'nip' => '010001',
            'name' => 'Existing',
            'position' => 'Staff',
            'email' => 'existing@example.com',
            'join_date' => '2026-01-01',
            'city' => 'Jakarta',
            'status' => 'active',
            'password' => 'password123',
        ]);

        $pending = $this->createPending($office, nip: '123456');

        $this->actingAs($super)->post(route('users.activate', $pending));

        $this->assertSame('123456', $pending->refresh()->nip);
        $this->assertDatabaseMissing('users', ['nip' => '010002']);
    }

    public function test_legacy_pending_user_with_null_nip_gets_generated_nip(): void
    {
        $office = $this->createOffice();
        $super = $this->createManager('super_admin', $office);
        $pending = $this->createPending($office, nip: null);

        $this->actingAs($super)->post(route('users.activate', $pending));

        $pending->refresh();
        $this->assertSame('active', $pending->status);
        $this->assertMatchesRegularExpression('/^010\d{3}$/', $pending->nip);
    }

    public function test_generated_fallback_does_not_use_soft_deleted_nip(): void
    {
        $office = $this->createOffice();
        $super = $this->createManager('super_admin', $office);

        $deleted = User::factory()->create(['office_id' => $office->id, 'nip' => '010001']);
        $deleted->delete();

        $pending = $this->createPending($office, nip: null);

        $this->actingAs($super)->post(route('users.activate', $pending));

        $this->assertSame('010002', $pending->refresh()->nip);
    }

    public function test_pending_tab_only_lists_pending_registrations(): void
    {
        $office = $this->createOffice();
        $admin = $this->createManager('admin', $office);
        $pending = $this->createPending($office, nip: '555555');
        $active = User::factory()->create(['office_id' => $office->id, 'status' => 'active']);

        $response = $this->actingAs($admin)->get(route('users.index', ['status' => 'pending']));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('users/index')
                ->where('filters.status', 'pending')
                ->has('users.data', 1)
                ->where('users.data.0.id', $pending->id));
    }

    public function test_admin_pending_tab_excludes_other_offices(): void
    {
        $adminOffice = $this->createOffice();
        $otherOffice = $this->createOffice();
        $admin = $this->createManager('admin', $adminOffice);
        $own = $this->createPending($adminOffice, nip: '555555');
        $other = $this->createPending($otherOffice, nip: '666666');

        $response = $this->actingAs($admin)->get(route('users.index', ['status' => 'pending']));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('users/index')
                ->has('users.data', 1)
                ->where('users.data.0.id', $own->id));

        $this->assertDatabaseHas('users', ['id' => $other->id, 'status' => 'pending']);
    }

    public function test_super_admin_pending_tab_lists_all_offices(): void
    {
        $officeA = $this->createOffice();
        $officeB = $this->createOffice();
        $super = $this->createManager('super_admin', $officeA);
        $this->createPending($officeA, nip: '555555');
        $this->createPending($officeB, nip: '666666');

        $response = $this->actingAs($super)->get(route('users.index', ['status' => 'pending']));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('users/index')
                ->has('users.data', 2));
    }

    public function test_user_listing_exposes_status_counts(): void
    {
        $office = $this->createOffice();
        $admin = $this->createManager('admin', $office);
        $this->createPending($office, nip: '555555');
        $this->createPending($office, nip: '666666');
        User::factory()->create(['office_id' => $office->id, 'status' => 'active']);
        User::factory()->create(['office_id' => $office->id, 'status' => 'rejected']);

        $response = $this->actingAs($admin)->get(route('users.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('users/index')
            ->where('counts.active', 2)
            ->where('counts.pending', 2)
            ->where('counts.rejected', 1));
    }

    public function test_default_tab_shows_active_accounts(): void
    {
        $office = $this->createOffice();
        $super = $this->createManager('super_admin', $office);
        $this->createPending($office, nip: '555555');
        User::factory()->create(['office_id' => $office->id, 'status' => 'active']);

        $response = $this->actingAs($super)->get(route('users.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('users/index')
            ->where('filters.status', 'active')
            ->has('users.data', 2)
            ->where('users.data.0.status', 'active')
            ->where('users.data.1.status', 'active'));
    }

    public function test_activation_clears_the_rejection_reason(): void
    {
        $office = $this->createOffice();
        $super = $this->createManager('super_admin', $office);
        $pending = $this->createPending($office, nip: '555555');

        $this->actingAs($super)->post(route('users.reject', $pending), ['rejected_reason' => 'Try again later']);
        $this->assertSame('rejected', $pending->refresh()->status);

        $pending->update(['status' => 'pending']);
        $this->actingAs($super)->post(route('users.activate', $pending));

        $pending->refresh();
        $this->assertSame('active', $pending->status);
        $this->assertNull($pending->rejected_reason);
        $this->assertNotNull($pending->nip);
    }

    public function test_dashboard_counts_pending_registrations_for_admins(): void
    {
        $office = $this->createOffice();
        $admin = $this->createManager('admin', $office);
        $otherOffice = $this->createOffice();
        $this->createPending($office, nip: '555555');
        $this->createPending($office, nip: '666666');
        $this->createPending($otherOffice, nip: '777777');

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('cards.4.id', 'pending_registrations')
                ->where('cards.4.value', 2));
    }

    public function test_dashboard_counts_all_pending_registrations_for_super_admins(): void
    {
        $office = $this->createOffice();
        $otherOffice = $this->createOffice();
        $super = $this->createManager('super_admin', $office);
        $this->createPending($office, nip: '555555');
        $this->createPending($otherOffice, nip: '666666');

        $response = $this->actingAs($super)->get(route('dashboard'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('cards.9.id', 'pending_registrations')
                ->where('cards.9.value', 2));
    }

    public function test_dashboard_total_employees_only_counts_active_users(): void
    {
        $office = $this->createOffice();
        $admin = $this->createManager('admin', $office);
        $this->createPending($office, nip: '555555');
        User::factory()->create(['office_id' => $office->id, 'status' => 'active']);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('cards.0.id', 'total_employees')
                ->where('cards.0.value', 2));
    }

    public function test_sidebar_pending_count_is_shared_for_managers_only(): void
    {
        $office = $this->createOffice();
        $admin = $this->createManager('admin', $office);
        $this->createPending($office, nip: '555555');

        $this->actingAs($admin)->get(route('users.index'))
            ->assertInertia(fn ($page) => $page->where('pending_registrations_count', 1));

        $employee = User::factory()->create(['office_id' => $office->id]);

        $this->actingAs($employee)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('pending_registrations_count', 0));
    }

    public function test_admin_can_view_a_pending_registration_detail_from_their_own_office(): void
    {
        $office = $this->createOffice();
        $admin = $this->createManager('admin', $office);
        $pending = $this->createPending($office, nip: '555555');

        $response = $this->actingAs($admin)->get(route('users.show', $pending));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('users/show')
                ->where('user.id', $pending->id)
                ->where('user.nip', '555555')
                ->where('user.status', 'pending')
                ->where('can.activate', true)
                ->where('can.reject', true));
    }

    public function test_admin_can_not_view_a_registration_from_another_office(): void
    {
        $adminOffice = $this->createOffice();
        $otherOffice = $this->createOffice();
        $admin = $this->createManager('admin', $adminOffice);
        $pending = $this->createPending($otherOffice, nip: '555555');

        $this->actingAs($admin)->get(route('users.show', $pending))->assertForbidden();
    }

    public function test_super_admin_can_view_a_pending_registration_from_any_office(): void
    {
        $office = $this->createOffice();
        $super = $this->createManager('super_admin', $office);
        $pending = $this->createPending($office, nip: '555555');

        $this->actingAs($super)->get(route('users.show', $pending))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('users/show')
                ->where('user.id', $pending->id));
    }

    public function test_regular_users_can_not_view_user_details(): void
    {
        $office = $this->createOffice();
        $employee = User::factory()->create(['office_id' => $office->id, 'status' => 'active']);
        $pending = $this->createPending($office, nip: '555555');

        $this->actingAs($employee)->get(route('users.show', $pending))->assertForbidden();
    }

    public function test_show_page_hides_review_actions_after_activation(): void
    {
        $office = $this->createOffice();
        $super = $this->createManager('super_admin', $office);
        $pending = $this->createPending($office, nip: '555555');

        $this->actingAs($super)->post(route('users.activate', $pending));

        $this->actingAs($super)->get(route('users.show', $pending))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('users/show')
                ->where('user.status', 'active')
                ->where('can.activate', false)
                ->where('can.reject', false));
    }

    public function test_register_submits_into_the_pending_tab_then_activate_preserves_nip_and_allows_login(): void
    {
        $office = $this->createOffice();
        $admin = $this->createManager('admin', $office);

        $this->post(route('register.store'), [
            'nip' => '123456',
            'office_id' => $office->id,
            'name' => 'End To End',
            'position' => 'Staff',
            'email' => 'end.to.end@example.com',
            'phone' => '08123456783',
            'join_date' => '2026-08-01',
            'city' => 'Jakarta',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login', absolute: false));

        $pending = User::query()->where('email', 'end.to.end@example.com')->firstOrFail();
        $this->assertSame('pending', $pending->status);
        $this->assertSame('123456', $pending->nip);

        $blocked = $this->post(route('login.store'), [
            'nip' => '123456',
            'password' => 'password123',
        ]);
        $blocked->assertSessionHasErrors('nip');
        $this->assertGuest();

        $this->actingAs($admin)->post(route('users.activate', $pending));

        $pending->refresh();
        $this->assertSame('active', $pending->status);
        $this->assertSame('123456', $pending->nip);

        $this->post(route('logout'));

        $this->post(route('login.store'), [
            'nip' => '123456',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($pending);
    }

    private function createPending(Office $office, ?string $nip = '555555'): User
    {
        return User::query()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => 'user'], ['name' => 'user'])->id,
            'office_id' => $office->id,
            'nip' => $nip,
            'name' => fake()->name(),
            'position' => 'Staff',
            'email' => fake()->unique()->safeEmail(),
            'join_date' => '2026-08-01',
            'city' => 'Jakarta',
            'status' => 'pending',
            'password' => 'password123',
        ]);
    }

    private function createManager(string $roleName, Office $office): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => $roleName], ['name' => $roleName])->id,
            'office_id' => $office->id,
            'status' => 'active',
        ]);
    }
}

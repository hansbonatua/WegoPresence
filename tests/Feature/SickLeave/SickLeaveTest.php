<?php

namespace Tests\Feature\SickLeave;

use App\Exceptions\SickLeaveException;
use App\Models\Role;
use App\Models\SickLeave;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\SickLeaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SickLeaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_their_own_sick_leaves(): void
    {
        $user = $this->createUser('user');
        $this->createSickLeave($user, ['reason' => 'Flu and fever']);

        $response = $this->actingAs($user)->get(route('sick-leaves.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('sick-leaves/index')
            ->where('sickLeaves.total', 1)
            ->has('sickLeaves.data', 1, fn ($item) => $item
                ->where('reason', 'Flu and fever')
                ->where('status', 'pending')
                ->etc()));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('sick-leaves.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_active_user_can_open_create_page(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->get(route('sick-leaves.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('sick-leaves/create'));
    }

    public function test_active_user_can_create_sick_leave_with_pending_status(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->post(route('sick-leaves.store'), $this->validPayload());

        $response->assertRedirect(route('sick-leaves.index'));

        $sickLeave = SickLeave::query()->firstOrFail();

        $this->assertSame($user->id, $sickLeave->user_id);
        $this->assertSame(today()->format('Y-m-d'), $sickLeave->start_date->format('Y-m-d'));
        $this->assertSame(today()->addDay()->format('Y-m-d'), $sickLeave->end_date->format('Y-m-d'));
        $this->assertSame('Sick with flu', $sickLeave->reason);
        $this->assertSame('pending', $sickLeave->status);
    }

    public function test_user_id_and_status_from_request_are_ignored(): void
    {
        $user = $this->createUser('user');
        $target = $this->createUser('user');

        $response = $this->actingAs($user)->post(route('sick-leaves.store'), [
            ...$this->validPayload(),
            'user_id' => $target->id,
            'status' => 'approved',
            'approved_by' => $target->id,
        ]);

        $response->assertRedirect(route('sick-leaves.index'));

        $sickLeave = SickLeave::query()->firstOrFail();

        $this->assertSame($user->id, $sickLeave->user_id);
        $this->assertSame('pending', $sickLeave->status);
        $this->assertNull($sickLeave->approved_by);
    }

    public function test_inactive_user_cannot_create_sick_leave(): void
    {
        $user = $this->createUser('user', ['status' => 'rejected']);

        $response = $this->actingAs($user)->post(route('sick-leaves.store'), $this->validPayload());

        $response->assertForbidden();
        $this->assertDatabaseCount('sick_leaves', 0);
    }

    public function test_sick_leave_rejects_past_start_date(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->post(route('sick-leaves.store'), [
            'start_date' => today()->subDay()->format('Y-m-d'),
            'end_date' => today()->format('Y-m-d'),
            'reason' => 'Sick with flu',
        ]);

        $response->assertSessionHasErrors('start_date');
    }

    public function test_sick_leave_rejects_end_date_before_start_date(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->post(route('sick-leaves.store'), [
            'start_date' => today()->addDay()->format('Y-m-d'),
            'end_date' => today()->format('Y-m-d'),
            'reason' => 'Sick with flu',
        ]);

        $response->assertSessionHasErrors('end_date');
        $this->assertDatabaseCount('sick_leaves', 0);
    }

    public function test_sick_leave_rejects_empty_reason(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->post(route('sick-leaves.store'), [
            'start_date' => today()->format('Y-m-d'),
            'end_date' => today()->format('Y-m-d'),
            'reason' => '',
        ]);

        $response->assertSessionHasErrors('reason');
    }

    public function test_owner_can_cancel_pending_sick_leave(): void
    {
        $user = $this->createUser('user');
        $sickLeave = $this->createSickLeave($user);

        $response = $this->actingAs($user)->post(route('sick-leaves.cancel', $sickLeave));

        $response->assertRedirect();
        $this->assertSame('cancelled', $sickLeave->fresh()->status);
    }

    public function test_owner_cannot_cancel_approved_sick_leave(): void
    {
        $user = $this->createUser('user');
        $sickLeave = $this->createSickLeave($user, ['status' => 'approved']);

        $response = $this->actingAs($user)->post(route('sick-leaves.cancel', $sickLeave));

        $response->assertForbidden();
        $this->assertSame('approved', $sickLeave->fresh()->status);
    }

    public function test_user_cannot_cancel_someone_elses_sick_leave(): void
    {
        $owner = $this->createUser('user');
        $other = $this->createUser('user');
        $sickLeave = $this->createSickLeave($owner);

        $response = $this->actingAs($other)->post(route('sick-leaves.cancel', $sickLeave));

        $response->assertForbidden();
        $this->assertSame('pending', $sickLeave->fresh()->status);
    }

    public function test_user_cannot_approve_sick_leave(): void
    {
        $owner = $this->createUser('user');
        $user = $this->createUser('user');
        $sickLeave = $this->createSickLeave($owner);

        $response = $this->actingAs($user)->post(route('sick-leaves.approve', $sickLeave));

        $response->assertForbidden();
    }

    public function test_user_cannot_reject_sick_leave(): void
    {
        $owner = $this->createUser('user');
        $user = $this->createUser('user');
        $sickLeave = $this->createSickLeave($owner);

        $response = $this->actingAs($user)->post(route('sick-leaves.reject', $sickLeave));

        $response->assertForbidden();
    }

    public function test_admin_can_view_all_sick_leaves(): void
    {
        $office = $this->createUser('user')->office;
        $staff = $this->createUser('user', ['office_id' => $office->id]);
        $admin = $this->createUser('admin', ['office_id' => $office->id]);
        $this->createSickLeave($staff, ['reason' => 'Staff flu']);
        $other = $this->createUser('user');
        $this->createSickLeave($other, ['reason' => 'Other flu']);

        $response = $this->actingAs($admin)->get(route('sick-leaves.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('sickLeaves.total', 2));
    }

    public function test_admin_can_approve_sick_leave(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $sickLeave = $this->createSickLeave($owner);

        $response = $this->actingAs($admin)->post(route('sick-leaves.approve', $sickLeave), [
            'approval_notes' => 'Get well soon',
        ]);

        $response->assertRedirect();

        $fresh = $sickLeave->fresh();

        $this->assertSame('approved', $fresh->status);
        $this->assertSame($admin->id, $fresh->approved_by);
        $this->assertSame('Get well soon', $fresh->approval_notes);
    }

    public function test_admin_can_reject_sick_leave(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $sickLeave = $this->createSickLeave($owner);

        $response = $this->actingAs($admin)->post(route('sick-leaves.reject', $sickLeave), [
            'approval_notes' => 'Medical certificate required',
        ]);

        $response->assertRedirect();

        $fresh = $sickLeave->fresh();

        $this->assertSame('rejected', $fresh->status);
        $this->assertSame($admin->id, $fresh->approved_by);
        $this->assertSame('Medical certificate required', $fresh->approval_notes);
    }

    public function test_approval_notes_are_optional(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $sickLeave = $this->createSickLeave($owner);

        $this->actingAs($admin)->post(route('sick-leaves.approve', $sickLeave));

        $fresh = $sickLeave->fresh();

        $this->assertSame('approved', $fresh->status);
        $this->assertSame($admin->id, $fresh->approved_by);
        $this->assertNull($fresh->approval_notes);
    }

    public function test_final_status_cannot_be_modified_again(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $sickLeave = $this->createSickLeave($owner);

        $this->actingAs($admin)->post(route('sick-leaves.approve', $sickLeave));

        $this->actingAs($admin)->post(route('sick-leaves.approve', $sickLeave))->assertForbidden();
        $this->actingAs($admin)->post(route('sick-leaves.reject', $sickLeave))->assertForbidden();
        $this->actingAs($owner)->post(route('sick-leaves.cancel', $sickLeave))->assertForbidden();

        $this->assertSame('approved', $sickLeave->fresh()->status);
    }

    public function test_super_admin_can_view_all_sick_leaves(): void
    {
        $userA = $this->createUser('user');
        $userB = $this->createUser('user');
        $this->createSickLeave($userA);
        $this->createSickLeave($userB);
        $superAdmin = $this->createUser('super_admin');

        $response = $this->actingAs($superAdmin)->get(route('sick-leaves.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('sickLeaves.total', 2));
    }

    public function test_sick_today_card_uses_real_database_data(): void
    {
        $owner = $this->createUser('user');
        $superAdmin = $this->createUser('super_admin');

        $this->createSickLeave($owner, [
            'status' => 'approved',
            'start_date' => today()->format('Y-m-d'),
            'end_date' => today()->format('Y-m-d'),
        ]);

        $data = app(DashboardService::class)->getDashboardData($superAdmin);

        $sickToday = collect($data['cards'])->firstWhere('id', 'sick_today');

        $this->assertSame(1, $sickToday['value']);
    }

    public function test_sick_today_ignores_non_approved_and_out_of_range(): void
    {
        $owner = $this->createUser('user');
        $superAdmin = $this->createUser('super_admin');

        $this->createSickLeave($owner, ['status' => 'pending']);
        $this->createSickLeave($owner, [
            'status' => 'approved',
            'start_date' => today()->addDays(3)->format('Y-m-d'),
            'end_date' => today()->addDays(5)->format('Y-m-d'),
        ]);

        $data = app(DashboardService::class)->getDashboardData($superAdmin);

        $sickToday = collect($data['cards'])->firstWhere('id', 'sick_today');

        $this->assertSame(0, $sickToday['value']);
    }

    public function test_dashboard_pending_changes_after_approval(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $sickLeave = $this->createSickLeave($owner);

        $before = app(DashboardService::class)->getDashboardData($admin);
        $pendingBefore = collect($before['cards'])->firstWhere('id', 'pending_approval');

        $this->assertSame(1, $pendingBefore['value']);

        app(SickLeaveService::class)->approve($admin, $sickLeave);

        $after = app(DashboardService::class)->getDashboardData($admin);
        $pendingAfter = collect($after['cards'])->firstWhere('id', 'pending_approval');

        $this->assertSame(0, $pendingAfter['value']);
    }

    public function test_cancelled_sick_leave_is_not_counted_as_pending_approval(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $sickLeave = $this->createSickLeave($owner);

        app(SickLeaveService::class)->cancel($owner, $sickLeave);

        $data = app(DashboardService::class)->getDashboardData($admin);
        $pending = collect($data['cards'])->firstWhere('id', 'pending_approval');

        $this->assertSame(0, $pending['value']);
    }

    public function test_sick_leave_appears_in_dashboard_recent_activity(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $this->createSickLeave($owner);

        $data = app(DashboardService::class)->getDashboardData($admin);

        $activity = collect($data['activities'])->firstWhere('type', 'sick_leave');

        $this->assertNotNull($activity);
        $this->assertSame('pending', $activity['status']);
        $this->assertSame(
            'Sick leave: '.today()->format('Y-m-d').' → '.today()->addDay()->format('Y-m-d'),
            $activity['title'],
        );
    }

    public function test_sick_leave_search_works(): void
    {
        $userA = $this->createUser('user', ['name' => 'Budi Santoso', 'nip' => '1234567890']);
        $userB = $this->createUser('user', ['name' => 'Siti Aminah', 'nip' => '9876543210']);
        $this->createSickLeave($userA, ['reason' => 'Flu and fever']);
        $this->createSickLeave($userB, ['reason' => 'Migraine']);
        $admin = $this->createUser('admin');

        $response = $this->actingAs($admin)->get(route('sick-leaves.index', ['search' => 'budi']));

        $response->assertInertia(fn ($page) => $page
            ->where('sickLeaves.total', 1));

        $response = $this->actingAs($admin)->get(route('sick-leaves.index', ['search' => 'migraine']));

        $response->assertInertia(fn ($page) => $page
            ->where('sickLeaves.total', 1));
    }

    public function test_sick_leave_status_filter_works(): void
    {
        $user = $this->createUser('user');
        $this->createSickLeave($user);
        $this->createSickLeave($user, ['status' => 'approved']);
        $admin = $this->createUser('admin');

        $response = $this->actingAs($admin)->get(route('sick-leaves.index', ['status' => 'approved']));

        $response->assertInertia(fn ($page) => $page
            ->where('sickLeaves.total', 1)
            ->where('sickLeaves.data.0.status', 'approved'));
    }

    public function test_sick_leaves_are_paginated_by_ten(): void
    {
        $user = $this->createUser('user');
        $admin = $this->createUser('admin');

        for ($i = 0; $i < 12; $i++) {
            $this->createSickLeave($user, [
                'reason' => 'Sick leave number '.$i,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('sick-leaves.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('sickLeaves.total', 12)
            ->where('sickLeaves.current_page', 1)
            ->where('sickLeaves.last_page', 2)
            ->has('sickLeaves.data', 10));
    }

    public function test_role_authorization_cannot_be_bypassed_via_direct_http(): void
    {
        $owner = $this->createUser('user');
        $attacker = $this->createUser('user');
        $sickLeave = $this->createSickLeave($owner);

        $this->actingAs($attacker)
            ->post(route('sick-leaves.cancel', $sickLeave))
            ->assertForbidden();
        $this->actingAs($attacker)
            ->post(route('sick-leaves.approve', $sickLeave))
            ->assertForbidden();
        $this->actingAs($attacker)
            ->post(route('sick-leaves.reject', $sickLeave))
            ->assertForbidden();

        $this->assertSame('pending', $sickLeave->fresh()->status);
    }

    public function test_service_rejects_modifying_finalized_sick_leave(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $sickLeave = $this->createSickLeave($owner);

        app(SickLeaveService::class)->approve($admin, $sickLeave);

        $this->expectException(SickLeaveException::class);

        app(SickLeaveService::class)->cancel($owner, $sickLeave);
    }

    public function test_policy_rules_are_consistent(): void
    {
        $owner = $this->createUser('user');
        $other = $this->createUser('user');
        $admin = $this->createUser('admin');
        $sickLeave = $this->createSickLeave($owner);

        $this->assertFalse(Gate::forUser($other)->allows('view', $sickLeave));
        $this->assertTrue(Gate::forUser($owner)->allows('view', $sickLeave));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $sickLeave));
        $this->assertTrue(Gate::forUser($owner)->allows('cancel', $sickLeave));
        $this->assertFalse(Gate::forUser($owner)->allows('approve', $sickLeave));
        $this->assertTrue(Gate::forUser($admin)->allows('approve', $sickLeave));
    }

    /**
     * @return array{start_date: string, end_date: string, reason: string}
     */
    private function validPayload(): array
    {
        return [
            'start_date' => today()->format('Y-m-d'),
            'end_date' => today()->addDay()->format('Y-m-d'),
            'reason' => 'Sick with flu',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(string $roleName, array $attributes = []): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['name' => $roleName]);

        return User::factory()->create(['role_id' => $role->id, ...$attributes]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSickLeave(User $user, array $overrides = []): SickLeave
    {
        return SickLeave::query()->create([...[
            'user_id' => $user->id,
            'start_date' => today()->format('Y-m-d'),
            'end_date' => today()->addDay()->format('Y-m-d'),
            'reason' => 'Sick with flu',
            'status' => 'pending',
        ], ...$overrides]);
    }
}

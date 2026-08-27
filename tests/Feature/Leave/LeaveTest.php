<?php

namespace Tests\Feature\Leave;

use App\Models\LeaveRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\LeaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_their_own_leaves(): void
    {
        $user = $this->createUser('user');
        $this->createLeave($user, ['reason' => 'Annual leave']);

        $response = $this->actingAs($user)->get(route('leaves.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('leaves/index')
            ->where('leaves.total', 1)
            ->has('leaves.data', 1, fn ($item) => $item
                ->where('reason', 'Annual leave')
                ->where('status', 'pending')
                ->etc()));
    }

    public function test_user_cannot_view_other_users_leave(): void
    {
        $owner = $this->createUser('user');
        $other = $this->createUser('user');
        $leave = $this->createLeave($owner, ['reason' => 'Secret leave reason']);

        Gate::forUser($other)->denies('view', $leave);

        $response = $this->actingAs($other)->get(route('leaves.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('leaves.total', 0));
    }

    public function test_active_user_can_create_leave(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->post(route('leaves.store'), [
            'start_date' => today()->format('Y-m-d'),
            'end_date' => today()->addDay()->format('Y-m-d'),
            'reason' => 'Annual leave to recharge',
        ]);

        $response->assertRedirect(route('leaves.index'));

        $leave = LeaveRequest::query()->firstOrFail();

        $this->assertSame($user->id, $leave->user_id);
        $this->assertSame(today()->format('Y-m-d'), $leave->start_date->format('Y-m-d'));
        $this->assertSame(today()->addDay()->format('Y-m-d'), $leave->end_date->format('Y-m-d'));
        $this->assertSame('Annual leave to recharge', $leave->reason);
        $this->assertSame('pending', $leave->status);
    }

    public function test_user_id_from_request_is_ignored(): void
    {
        $user = $this->createUser('user');
        $target = $this->createUser('user');

        $this->actingAs($user)->post(
            route('leaves.store'),
            [...$this->validPayload(), 'user_id' => $target->id],
        );

        $this->assertSame($user->id, LeaveRequest::query()->firstOrFail()->user_id);
    }

    public function test_status_from_request_is_ignored_and_always_pending(): void
    {
        $user = $this->createUser('user');

        $this->actingAs($user)->post(
            route('leaves.store'),
            [...$this->validPayload(), 'status' => 'approved', 'approved_by' => 999],
        );

        $leave = LeaveRequest::query()->firstOrFail();

        $this->assertSame('pending', $leave->status);
        $this->assertNull($leave->approved_by);
    }

    public function test_invalid_date_format_is_rejected(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->post(route('leaves.store'), [
            ...$this->validPayload(),
            'start_date' => 'not-a-date',
        ]);

        $response->assertSessionHasErrors('start_date');
        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->post(route('leaves.store'), [
            ...$this->validPayload(),
            'end_date' => today()->subDay()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('end_date');
        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_empty_reason_is_rejected(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->post(route('leaves.store'), [
            ...$this->validPayload(),
            'reason' => '',
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_inactive_user_cannot_create_leave(): void
    {
        $user = $this->createUser('user', ['status' => 'pending']);

        $response = $this->actingAs($user)->post(
            route('leaves.store'),
            $this->validPayload(),
        );

        $response->assertForbidden();
        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_user_can_cancel_their_pending_leave(): void
    {
        $user = $this->createUser('user');
        $leave = $this->createLeave($user);

        $response = $this->actingAs($user)->post(route('leaves.cancel', $leave));

        $response->assertRedirect();
        $this->assertSame('cancelled', $leave->fresh()->status);
    }

    public function test_user_cannot_cancel_an_approved_leave(): void
    {
        $user = $this->createUser('user');
        $leave = $this->createLeave($user, ['status' => 'approved']);

        $response = $this->actingAs($user)->post(route('leaves.cancel', $leave));

        $response->assertForbidden();
        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_user_cannot_approve_leave(): void
    {
        $owner = $this->createUser('user');
        $user = $this->createUser('user');
        $leave = $this->createLeave($owner);

        $response = $this->actingAs($user)->post(route('leaves.approve', $leave));

        $response->assertForbidden();
        $this->assertSame('pending', $leave->fresh()->status);
    }

    public function test_user_cannot_reject_leave(): void
    {
        $owner = $this->createUser('user');
        $user = $this->createUser('user');
        $leave = $this->createLeave($owner);

        $response = $this->actingAs($user)->post(route('leaves.reject', $leave));

        $response->assertForbidden();
        $this->assertSame('pending', $leave->fresh()->status);
    }

    public function test_admin_can_view_all_leaves(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $leave = $this->createLeave($owner, ['reason' => 'Admin visible leave']);

        $response = $this->actingAs($admin)->get(route('leaves.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('leaves.total', 1)
            ->has('leaves.data', 1, fn ($item) => $item
                ->where('id', $leave->id)
                ->where('reason', 'Admin visible leave')
                ->etc()));
    }

    public function test_admin_can_approve_pending_leave(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $leave = $this->createLeave($owner);

        $response = $this->actingAs($admin)->post(
            route('leaves.approve', $leave),
            ['approval_notes' => 'Approved by HR'],
        );

        $response->assertRedirect();

        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertSame($admin->id, $leave->fresh()->approved_by);
        $this->assertSame('Approved by HR', $leave->fresh()->approval_notes);
    }

    public function test_admin_can_reject_pending_leave(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $leave = $this->createLeave($owner);

        $response = $this->actingAs($admin)->post(
            route('leaves.reject', $leave),
            ['approval_notes' => 'Missing documentation'],
        );

        $response->assertRedirect();

        $this->assertSame('rejected', $leave->fresh()->status);
        $this->assertSame($admin->id, $leave->fresh()->approved_by);
        $this->assertSame('Missing documentation', $leave->fresh()->approval_notes);
    }

    public function test_admin_cannot_process_a_final_leave(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $approved = $this->createLeave($owner, ['status' => 'approved']);
        $rejected = $this->createLeave($owner, ['status' => 'rejected']);
        $cancelled = $this->createLeave($owner, ['status' => 'cancelled']);

        $this->actingAs($admin)->post(route('leaves.approve', $approved))->assertForbidden();
        $this->actingAs($admin)->post(route('leaves.reject', $rejected))->assertForbidden();
        $this->actingAs($admin)->post(route('leaves.reject', $cancelled))->assertForbidden();

        $this->assertSame('approved', $approved->fresh()->status);
        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
    }

    public function test_super_admin_can_approve_leave(): void
    {
        $owner = $this->createUser('user');
        $superAdmin = $this->createUser('super_admin');
        $leave = $this->createLeave($owner);

        $this->actingAs($superAdmin)
            ->post(route('leaves.approve', $leave))
            ->assertRedirect();

        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertSame($superAdmin->id, $leave->fresh()->approved_by);
    }

    public function test_super_admin_can_reject_leave(): void
    {
        $owner = $this->createUser('user');
        $superAdmin = $this->createUser('super_admin');
        $leave = $this->createLeave($owner);

        $this->actingAs($superAdmin)
            ->post(route('leaves.reject', $leave))
            ->assertRedirect();

        $this->assertSame('rejected', $leave->fresh()->status);
        $this->assertSame($superAdmin->id, $leave->fresh()->approved_by);
    }

    public function test_approved_by_is_stored_correctly(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $leave = $this->createLeave($owner);

        app(LeaveService::class)->approve($admin, $leave);

        $this->assertSame($admin->id, $leave->fresh()->approved_by);
        $this->assertTrue($leave->fresh()->approver->is($admin));
    }

    public function test_approval_notes_are_stored(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $leave = $this->createLeave($owner);

        app(LeaveService::class)->reject($admin, $leave, 'No quota remaining');

        $this->assertSame('No quota remaining', $leave->fresh()->approval_notes);
    }

    public function test_cancelled_leave_is_not_counted_as_pending_approval(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $leave = $this->createLeave($owner);

        app(LeaveService::class)->cancel($owner, $leave);

        $data = app(DashboardService::class)->getDashboardData($admin);
        $pending = collect($data['cards'])->firstWhere('id', 'pending_approval');

        $this->assertSame(0, $pending['value']);
    }

    public function test_dashboard_pending_leave_changes_after_approval(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $leave = $this->createLeave($owner);

        $before = app(DashboardService::class)->getDashboardData($admin);
        $pendingBefore = collect($before['cards'])->firstWhere('id', 'pending_approval');

        $this->assertSame(1, $pendingBefore['value']);

        app(LeaveService::class)->approve($admin, $leave);

        $after = app(DashboardService::class)->getDashboardData($admin);
        $pendingAfter = collect($after['cards'])->firstWhere('id', 'pending_approval');

        $this->assertSame(0, $pendingAfter['value']);
    }

    public function test_leave_today_card_uses_real_database_data(): void
    {
        $owner = $this->createUser('user');
        $superAdmin = $this->createUser('super_admin');

        $this->createLeave($owner, [
            'status' => 'approved',
            'start_date' => today()->format('Y-m-d'),
            'end_date' => today()->format('Y-m-d'),
        ]);

        $data = app(DashboardService::class)->getDashboardData($superAdmin);

        $leaveToday = collect($data['cards'])->firstWhere('id', 'leave_today');

        $this->assertSame(1, $leaveToday['value']);
    }

    public function test_role_authorization_cannot_be_bypassed_via_direct_http(): void
    {
        $owner = $this->createUser('user');
        $attacker = $this->createUser('user');
        $leave = $this->createLeave($owner);

        $this->actingAs($attacker)
            ->post(route('leaves.cancel', $leave))
            ->assertForbidden();
        $this->actingAs($attacker)
            ->post(route('leaves.approve', $leave))
            ->assertForbidden();
        $this->actingAs($attacker)
            ->post(route('leaves.reject', $leave))
            ->assertForbidden();

        $this->assertSame('pending', $leave->fresh()->status);
        $this->assertNull($leave->fresh()->approved_by);
    }

    /**
     * @return array{start_date: string, end_date: string, reason: string}
     */
    private function validPayload(): array
    {
        return [
            'start_date' => today()->format('Y-m-d'),
            'end_date' => today()->format('Y-m-d'),
            'reason' => 'Annual leave',
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
    private function createLeave(User $user, array $overrides = []): LeaveRequest
    {
        return LeaveRequest::query()->create([...[
            'user_id' => $user->id,
            'start_date' => today()->format('Y-m-d'),
            'end_date' => today()->format('Y-m-d'),
            'reason' => 'Annual leave',
            'status' => 'pending',
        ], ...$overrides]);
    }
}

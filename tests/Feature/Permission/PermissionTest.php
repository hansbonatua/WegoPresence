<?php

namespace Tests\Feature\Permission;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_their_own_permissions(): void
    {
        $user = $this->createUser('user');
        $this->createPermission($user, ['reason' => 'Family event']);

        $response = $this->actingAs($user)->get(route('permissions.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('permissions/index')
            ->where('permissions.total', 1)
            ->has('permissions.data', 1, fn ($item) => $item
                ->where('reason', 'Family event')
                ->where('status', 'pending')
                ->etc()));
    }

    public function test_user_cannot_view_other_users_permission(): void
    {
        $owner = $this->createUser('user');
        $other = $this->createUser('user');
        $permission = $this->createPermission($owner, ['reason' => 'Secret reason']);

        Gate::forUser($other)->denies('view', $permission);

        $response = $this->actingAs($other)->get(route('permissions.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('permissions.total', 0));
    }

    public function test_user_can_create_permission(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->post(route('permissions.store'), [
            'type' => 'official',
            'start_date' => today()->format('Y-m-d'),
            'end_date' => today()->addDay()->format('Y-m-d'),
            'reason' => 'Company meeting outside office',
        ]);

        $response->assertRedirect(route('permissions.index'));

        $permission = Permission::query()->firstOrFail();

        $this->assertSame($user->id, $permission->user_id);
        $this->assertSame('official', $permission->type);
        $this->assertSame(today()->format('Y-m-d'), $permission->start_date->format('Y-m-d'));
        $this->assertSame(today()->addDay()->format('Y-m-d'), $permission->end_date->format('Y-m-d'));
        $this->assertSame('Company meeting outside office', $permission->reason);
    }

    public function test_new_permission_always_starts_as_pending(): void
    {
        $user = $this->createUser('user');

        $this->actingAs($user)->post(route('permissions.store'), $this->validPayload());

        $this->assertSame('pending', Permission::query()->firstOrFail()->status);
    }

    public function test_user_cannot_set_the_status_on_create(): void
    {
        $user = $this->createUser('user');

        $this->actingAs($user)->post(
            route('permissions.store'),
            [...$this->validPayload(), 'status' => 'approved'],
        );

        $this->assertSame('pending', Permission::query()->firstOrFail()->status);
        $this->assertNull(Permission::query()->firstOrFail()->approved_by);
    }

    public function test_user_id_from_request_is_ignored(): void
    {
        $user = $this->createUser('user');
        $target = $this->createUser('user');

        $this->actingAs($user)->post(
            route('permissions.store'),
            [...$this->validPayload(), 'user_id' => $target->id],
        );

        $this->assertSame($user->id, Permission::query()->firstOrFail()->user_id);
    }

    public function test_user_can_cancel_their_pending_permission(): void
    {
        $user = $this->createUser('user');
        $permission = $this->createPermission($user);

        $response = $this->actingAs($user)->post(
            route('permissions.cancel', $permission),
        );

        $response->assertRedirect();
        $this->assertSame('cancelled', $permission->fresh()->status);
    }

    public function test_user_cannot_cancel_an_approved_permission(): void
    {
        $user = $this->createUser('user');
        $permission = $this->createPermission($user, ['status' => 'approved']);

        $response = $this->actingAs($user)->post(
            route('permissions.cancel', $permission),
        );

        $response->assertForbidden();
        $this->assertSame('approved', $permission->fresh()->status);
    }

    public function test_user_cannot_approve_permission(): void
    {
        $owner = $this->createUser('user');
        $user = $this->createUser('user');
        $permission = $this->createPermission($owner);

        $response = $this->actingAs($user)->post(
            route('permissions.approve', $permission),
        );

        $response->assertForbidden();
        $this->assertSame('pending', $permission->fresh()->status);
    }

    public function test_user_cannot_reject_permission(): void
    {
        $owner = $this->createUser('user');
        $user = $this->createUser('user');
        $permission = $this->createPermission($owner);

        $response = $this->actingAs($user)->post(
            route('permissions.reject', $permission),
        );

        $response->assertForbidden();
        $this->assertSame('pending', $permission->fresh()->status);
    }

    public function test_admin_can_view_all_permissions(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $permission = $this->createPermission($owner, ['reason' => 'Admin visible reason']);

        $response = $this->actingAs($admin)->get(route('permissions.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('permissions.total', 1)
            ->has('permissions.data', 1, fn ($item) => $item
                ->where('id', $permission->id)
                ->where('reason', 'Admin visible reason')
                ->etc()));
    }

    public function test_admin_can_approve_permission(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $permission = $this->createPermission($owner);

        $response = $this->actingAs($admin)->post(
            route('permissions.approve', $permission),
            ['approval_notes' => 'Looks good'],
        );

        $response->assertRedirect();

        $this->assertSame('approved', $permission->fresh()->status);
        $this->assertSame($admin->id, $permission->fresh()->approved_by);
        $this->assertSame('Looks good', $permission->fresh()->approval_notes);
    }

    public function test_admin_can_reject_permission(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $permission = $this->createPermission($owner);

        $response = $this->actingAs($admin)->post(
            route('permissions.reject', $permission),
            ['approval_notes' => 'Not enough detail'],
        );

        $response->assertRedirect();

        $this->assertSame('rejected', $permission->fresh()->status);
        $this->assertSame($admin->id, $permission->fresh()->approved_by);
        $this->assertSame('Not enough detail', $permission->fresh()->approval_notes);
    }

    public function test_admin_cannot_modify_a_finalized_permission(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $approved = $this->createPermission($owner, ['status' => 'approved']);
        $rejected = $this->createPermission($owner, ['status' => 'rejected']);

        $this->actingAs($admin)
            ->post(route('permissions.approve', $approved))
            ->assertForbidden();
        $this->actingAs($admin)
            ->post(route('permissions.reject', $rejected))
            ->assertForbidden();

        $this->assertSame('approved', $approved->fresh()->status);
        $this->assertSame('rejected', $rejected->fresh()->status);
    }

    public function test_super_admin_can_view_all_permissions(): void
    {
        $owner = $this->createUser('user');
        $superAdmin = $this->createUser('super_admin');
        $permission = $this->createPermission($owner, ['reason' => 'Super admin visible reason']);

        $response = $this->actingAs($superAdmin)->get(route('permissions.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('permissions.total', 1)
            ->has('permissions.data', 1, fn ($item) => $item
                ->where('id', $permission->id)
                ->etc()));
    }

    public function test_super_admin_can_approve_permission(): void
    {
        $owner = $this->createUser('user');
        $superAdmin = $this->createUser('super_admin');
        $permission = $this->createPermission($owner);

        $this->actingAs($superAdmin)
            ->post(route('permissions.approve', $permission))
            ->assertRedirect();

        $this->assertSame('approved', $permission->fresh()->status);
        $this->assertSame($superAdmin->id, $permission->fresh()->approved_by);
    }

    public function test_super_admin_can_reject_permission(): void
    {
        $owner = $this->createUser('user');
        $superAdmin = $this->createUser('super_admin');
        $permission = $this->createPermission($owner);

        $this->actingAs($superAdmin)
            ->post(route('permissions.reject', $permission))
            ->assertRedirect();

        $this->assertSame('rejected', $permission->fresh()->status);
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->post(route('permissions.store'), [
            ...$this->validPayload(),
            'end_date' => today()->subDay()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('end_date');
        $this->assertDatabaseCount('permissions', 0);
    }

    public function test_invalid_date_format_is_rejected(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->post(route('permissions.store'), [
            ...$this->validPayload(),
            'start_date' => 'not-a-date',
        ]);

        $response->assertSessionHasErrors('start_date');
        $this->assertDatabaseCount('permissions', 0);
    }

    public function test_empty_reason_is_rejected(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->post(route('permissions.store'), [
            ...$this->validPayload(),
            'reason' => '',
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('permissions', 0);
    }

    public function test_invalid_type_is_rejected(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->post(route('permissions.store'), [
            ...$this->validPayload(),
            'type' => 'holiday',
        ]);

        $response->assertSessionHasErrors('type');
        $this->assertDatabaseCount('permissions', 0);
    }

    public function test_permission_today_card_uses_real_database_data(): void
    {
        $owner = $this->createUser('user');
        $superAdmin = $this->createUser('super_admin');

        $this->createPermission($owner, [
            'status' => 'approved',
            'start_date' => today()->format('Y-m-d'),
            'end_date' => today()->format('Y-m-d'),
        ]);

        $data = app(DashboardService::class)->getDashboardData($superAdmin);

        $permissionToday = collect($data['cards'])->firstWhere('id', 'permission_today');

        $this->assertSame(1, $permissionToday['value']);
    }

    public function test_pending_approval_card_changes_after_approval(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $permission = $this->createPermission($owner);

        $before = app(DashboardService::class)->getDashboardData($admin);
        $pendingBefore = collect($before['cards'])->firstWhere('id', 'pending_approval');

        $this->assertSame(1, $pendingBefore['value']);

        app(PermissionService::class)->approve($admin, $permission);

        $after = app(DashboardService::class)->getDashboardData($admin);
        $pendingAfter = collect($after['cards'])->firstWhere('id', 'pending_approval');

        $this->assertSame(0, $pendingAfter['value']);
    }

    public function test_cancelled_permission_is_not_counted_as_pending(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $permission = $this->createPermission($owner);

        app(PermissionService::class)->cancel($owner, $permission);

        $data = app(DashboardService::class)->getDashboardData($admin);
        $pending = collect($data['cards'])->firstWhere('id', 'pending_approval');

        $this->assertSame(0, $pending['value']);
    }

    /**
     * @return array{type: string, start_date: string, end_date: string, reason: string}
     */
    private function validPayload(): array
    {
        return [
            'type' => 'personal',
            'start_date' => today()->format('Y-m-d'),
            'end_date' => today()->format('Y-m-d'),
            'reason' => 'Family event',
        ];
    }

    private function createUser(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['name' => $roleName]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPermission(User $user, array $overrides = []): Permission
    {
        return Permission::query()->create([...[
            'user_id' => $user->id,
            'type' => 'personal',
            'start_date' => today()->format('Y-m-d'),
            'end_date' => today()->format('Y-m-d'),
            'reason' => 'Family event',
            'status' => 'pending',
        ], ...$overrides]);
    }
}

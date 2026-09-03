<?php

namespace Tests\Feature\AttendanceComplaint;

use App\Exceptions\AttendanceComplaintException;
use App\Models\Attendance;
use App\Models\AttendanceComplaint;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Services\AttendanceComplaintService;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AttendanceComplaintTest extends TestCase
{
    use RefreshDatabase;

    private int $attendanceCounter = 0;

    public function test_authenticated_user_can_view_their_own_complaints(): void
    {
        $user = $this->createUser('user');
        $this->createComplaint($user, $this->createAttendance($user));

        $response = $this->actingAs($user)->get(route('attendance-complaints.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('attendance-complaints/index')
            ->where('complaints.total', 1)
            ->has('complaints.data', 1, fn ($item) => $item
                ->where('complaint_reason', 'I forgot to check in.')
                ->where('status', 'pending')
                ->etc()));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('attendance-complaints.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_active_user_can_open_create_page(): void
    {
        $user = $this->createUser('user');
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)->get(route('attendance-complaints.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('attendance-complaints/create')
            ->where('attendances.0.id', $attendance->id));
    }

    public function test_active_user_can_create_complaint_with_pending_status(): void
    {
        $user = $this->createUser('user');
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)
            ->post(route('attendance-complaints.store'), [
                'attendance_id' => $attendance->id,
                'complaint_reason' => 'The app showed me as late but I was on time.',
            ]);

        $response->assertRedirect(route('attendance-complaints.index'));

        $complaint = AttendanceComplaint::query()->firstOrFail();

        $this->assertSame($attendance->id, $complaint->attendance_id);
        $this->assertSame($user->id, $complaint->user_id);
        $this->assertSame('The app showed me as late but I was on time.', $complaint->complaint_reason);
        $this->assertSame('pending', $complaint->status);
        $this->assertNull($complaint->approved_by);
        $this->assertNull($complaint->approval_notes);
    }

    public function test_user_id_and_status_from_request_are_ignored(): void
    {
        $user = $this->createUser('user');
        $target = $this->createUser('user');
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)
            ->post(route('attendance-complaints.store'), [
                'attendance_id' => $attendance->id,
                'complaint_reason' => 'Wrong status recorded.',
                'user_id' => $target->id,
                'status' => 'approved',
                'approved_by' => $target->id,
                'approval_notes' => 'Fake note',
            ]);

        $response->assertRedirect(route('attendance-complaints.index'));

        $complaint = AttendanceComplaint::query()->firstOrFail();

        $this->assertSame($user->id, $complaint->user_id);
        $this->assertSame('pending', $complaint->status);
        $this->assertNull($complaint->approved_by);
        $this->assertNull($complaint->approval_notes);
    }

    public function test_user_cannot_complain_about_someone_elses_attendance(): void
    {
        $user = $this->createUser('user');
        $other = $this->createUser('user');
        $attendance = $this->createAttendance($other);

        $response = $this->actingAs($user)
            ->post(route('attendance-complaints.store'), [
                'attendance_id' => $attendance->id,
                'complaint_reason' => 'Trying to complain about another user.',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('attendance_complaints', 0);
    }

    public function test_complaint_rejects_nonexistent_attendance(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)
            ->post(route('attendance-complaints.store'), [
                'attendance_id' => 999,
                'complaint_reason' => 'Does not matter.',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('attendance_complaints', 0);
    }

    public function test_complaint_rejects_empty_reason(): void
    {
        $user = $this->createUser('user');
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)
            ->post(route('attendance-complaints.store'), [
                'attendance_id' => $attendance->id,
                'complaint_reason' => '',
            ]);

        $response->assertSessionHasErrors('complaint_reason');
        $this->assertDatabaseCount('attendance_complaints', 0);
    }

    public function test_duplicate_pending_complaint_for_same_attendance_is_rejected(): void
    {
        $user = $this->createUser('user');
        $attendance = $this->createAttendance($user);
        $this->createComplaint($user, $attendance);

        $response = $this->actingAs($user)
            ->post(route('attendance-complaints.store'), [
                'attendance_id' => $attendance->id,
                'complaint_reason' => 'Second complaint for the same record.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'You already submitted a complaint for this attendance record.');
        $this->assertDatabaseCount('attendance_complaints', 1);
    }

    public function test_complaint_after_rejection_can_be_submitted_again(): void
    {
        $user = $this->createUser('user');
        $attendance = $this->createAttendance($user);
        $this->createComplaint($user, $attendance, ['status' => 'rejected']);

        $response = $this->actingAs($user)
            ->post(route('attendance-complaints.store'), [
                'attendance_id' => $attendance->id,
                'complaint_reason' => 'New complaint after rejection.',
            ]);

        $response->assertRedirect(route('attendance-complaints.index'));
        $this->assertDatabaseCount('attendance_complaints', 2);
    }

    public function test_inactive_user_cannot_create_complaint(): void
    {
        $user = $this->createUser('user', ['status' => 'rejected']);
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)
            ->post(route('attendance-complaints.store'), [
                'attendance_id' => $attendance->id,
                'complaint_reason' => 'Should not be allowed.',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('attendance_complaints', 0);
    }

    public function test_owner_can_cancel_pending_complaint(): void
    {
        $user = $this->createUser('user');
        $complaint = $this->createComplaint($user, $this->createAttendance($user));

        $response = $this->actingAs($user)
            ->post(route('attendance-complaints.cancel', $complaint));

        $response->assertRedirect();
        $this->assertSoftDeleted('attendance_complaints', ['id' => $complaint->id]);
    }

    public function test_owner_cannot_cancel_approved_complaint(): void
    {
        $user = $this->createUser('user');
        $complaint = $this->createComplaint($user, $this->createAttendance($user), ['status' => 'approved']);

        $response = $this->actingAs($user)
            ->post(route('attendance-complaints.cancel', $complaint));

        $response->assertForbidden();
        $this->assertSame('approved', $complaint->fresh()->status);
    }

    public function test_user_cannot_cancel_someone_elses_complaint(): void
    {
        $owner = $this->createUser('user');
        $other = $this->createUser('user');
        $complaint = $this->createComplaint($owner, $this->createAttendance($owner));

        $response = $this->actingAs($other)
            ->post(route('attendance-complaints.cancel', $complaint));

        $response->assertForbidden();
        $this->assertSame('pending', $complaint->fresh()->status);
    }

    public function test_user_cannot_approve_or_reject_complaint(): void
    {
        $owner = $this->createUser('user');
        $user = $this->createUser('user');
        $complaint = $this->createComplaint($owner, $this->createAttendance($owner));

        $this->actingAs($user)
            ->post(route('attendance-complaints.approve', $complaint))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('attendance-complaints.reject', $complaint))
            ->assertForbidden();
    }

    public function test_admin_can_view_only_own_office_complaints(): void
    {
        $officeA = $this->factoryOffice();
        $officeB = Office::query()->create([
            'office_code' => 'JKT002',
            'office_name' => 'Branch Office',
            'city' => 'Bandung',
            'address' => 'Bandung',
            'status' => 'active',
        ]);

        $staffA = $this->createUser('user', ['office_id' => $officeA->id]);
        $staffB = $this->createUser('user', ['office_id' => $officeB->id]);
        $admin = $this->createUser('admin', ['office_id' => $officeA->id]);

        $this->createComplaint($staffA, $this->createAttendance($staffA), ['complaint_reason' => 'Office A complaint']);
        $this->createComplaint($staffB, $this->createAttendance($staffB), ['complaint_reason' => 'Office B complaint']);

        $response = $this->actingAs($admin)->get(route('attendance-complaints.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('complaints.total', 1)
            ->where('complaints.data.0.complaint_reason', 'Office A complaint'));
    }

    public function test_admin_cannot_approve_complaint_from_another_office(): void
    {
        $officeA = $this->factoryOffice();
        $officeB = Office::query()->create([
            'office_code' => 'JKT002',
            'office_name' => 'Branch Office',
            'city' => 'Bandung',
            'address' => 'Bandung',
            'status' => 'active',
        ]);

        $staffB = $this->createUser('user', ['office_id' => $officeB->id]);
        $admin = $this->createUser('admin', ['office_id' => $officeA->id]);
        $complaint = $this->createComplaint($staffB, $this->createAttendance($staffB));

        $response = $this->actingAs($admin)
            ->post(route('attendance-complaints.approve', $complaint));

        $response->assertForbidden();
        $this->assertSame('pending', $complaint->fresh()->status);
    }

    public function test_admin_can_approve_complaint_with_notes(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $complaint = $this->createComplaint($owner, $this->createAttendance($owner));

        $response = $this->actingAs($admin)
            ->post(route('attendance-complaints.approve', $complaint), [
                'approval_notes' => 'Verified with CCTV',
            ]);

        $response->assertRedirect();

        $fresh = $complaint->fresh();

        $this->assertSame('approved', $fresh->status);
        $this->assertSame($admin->id, $fresh->approved_by);
        $this->assertSame('Verified with CCTV', $fresh->approval_notes);
    }

    public function test_admin_can_reject_complaint_with_notes(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $complaint = $this->createComplaint($owner, $this->createAttendance($owner));

        $response = $this->actingAs($admin)
            ->post(route('attendance-complaints.reject', $complaint), [
                'approval_notes' => 'No evidence provided',
            ]);

        $response->assertRedirect();

        $fresh = $complaint->fresh();

        $this->assertSame('rejected', $fresh->status);
        $this->assertSame($admin->id, $fresh->approved_by);
        $this->assertSame('No evidence provided', $fresh->approval_notes);
    }

    public function test_approval_notes_are_optional(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $complaint = $this->createComplaint($owner, $this->createAttendance($owner));

        $this->actingAs($admin)
            ->post(route('attendance-complaints.approve', $complaint));

        $fresh = $complaint->fresh();

        $this->assertSame('approved', $fresh->status);
        $this->assertSame($admin->id, $fresh->approved_by);
        $this->assertNull($fresh->approval_notes);
    }

    public function test_final_status_cannot_be_modified_again(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $complaint = $this->createComplaint($owner, $this->createAttendance($owner));

        $this->actingAs($admin)
            ->post(route('attendance-complaints.approve', $complaint));

        $this->actingAs($admin)
            ->post(route('attendance-complaints.approve', $complaint))
            ->assertForbidden();
        $this->actingAs($admin)
            ->post(route('attendance-complaints.reject', $complaint))
            ->assertForbidden();
        $this->actingAs($owner)
            ->post(route('attendance-complaints.cancel', $complaint))
            ->assertForbidden();

        $this->assertSame('approved', $complaint->fresh()->status);
    }

    public function test_super_admin_can_view_and_review_all_complaints(): void
    {
        $userA = $this->createUser('user');
        $userB = $this->createUser('user');
        $complaintA = $this->createComplaint($userA, $this->createAttendance($userA));
        $this->createComplaint($userB, $this->createAttendance($userB));
        $superAdmin = $this->createUser('super_admin');

        $response = $this->actingAs($superAdmin)->get(route('attendance-complaints.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('complaints.total', 2));

        $this->actingAs($superAdmin)
            ->post(route('attendance-complaints.approve', $complaintA))
            ->assertRedirect();
        $this->assertSame('approved', $complaintA->fresh()->status);
    }

    public function test_approval_does_not_change_the_attendance_record(): void
    {
        $owner = $this->createUser('user');
        $attendance = $this->createAttendance($owner, ['attendance_status' => 'late']);
        $admin = $this->createUser('admin');
        $complaint = $this->createComplaint($owner, $attendance);

        $this->actingAs($admin)
            ->post(route('attendance-complaints.approve', $complaint));

        $this->assertSame('late', $attendance->fresh()->attendance_status);
        $this->assertSame('approved', $complaint->fresh()->status);
    }

    public function test_search_works_for_reason_and_employee(): void
    {
        $userA = $this->createUser('user', ['name' => 'Budi Santoso', 'nip' => '1234567890']);
        $userB = $this->createUser('user', ['name' => 'Siti Aminah', 'nip' => '9876543210']);
        $this->createComplaint($userA, $this->createAttendance($userA), ['complaint_reason' => 'Wrong check-in time']);
        $this->createComplaint($userB, $this->createAttendance($userB), ['complaint_reason' => 'Missing check-out']);
        $admin = $this->createUser('admin');

        $response = $this->actingAs($admin)
            ->get(route('attendance-complaints.index', ['search' => 'budi']));

        $response->assertInertia(fn ($page) => $page
            ->where('complaints.total', 1));

        $response = $this->actingAs($admin)
            ->get(route('attendance-complaints.index', ['search' => 'missing']));

        $response->assertInertia(fn ($page) => $page
            ->where('complaints.total', 1));
    }

    public function test_status_filter_works(): void
    {
        $user = $this->createUser('user');
        $this->createComplaint($user, $this->createAttendance($user));
        $this->createComplaint($user, $this->createAttendance($user), ['status' => 'approved']);
        $admin = $this->createUser('admin');

        $response = $this->actingAs($admin)
            ->get(route('attendance-complaints.index', ['status' => 'approved']));

        $response->assertInertia(fn ($page) => $page
            ->where('complaints.total', 1)
            ->where('complaints.data.0.status', 'approved'));
    }

    public function test_complaints_are_paginated_by_ten(): void
    {
        $user = $this->createUser('user');
        $admin = $this->createUser('admin');

        for ($i = 0; $i < 12; $i++) {
            $this->createComplaint($user, $this->createAttendance($user), [
                'complaint_reason' => 'Complaint number '.$i,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('attendance-complaints.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('complaints.total', 12)
            ->where('complaints.current_page', 1)
            ->where('complaints.last_page', 2)
            ->has('complaints.data', 10));
    }

    public function test_cancelled_complaint_is_not_counted_as_pending_approval(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $complaint = $this->createComplaint($owner, $this->createAttendance($owner));

        app(AttendanceComplaintService::class)->cancel($owner, $complaint);

        $data = app(DashboardService::class)->getDashboardData($admin);
        $pending = collect($data['cards'])->firstWhere('id', 'pending_approval');

        $this->assertSame(0, $pending['value']);
    }

    public function test_dashboard_pending_changes_after_complaint_approval(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $complaint = $this->createComplaint($owner, $this->createAttendance($owner));

        $before = app(DashboardService::class)->getDashboardData($admin);
        $pendingBefore = collect($before['cards'])->firstWhere('id', 'pending_approval');

        $this->assertSame(1, $pendingBefore['value']);

        app(AttendanceComplaintService::class)->approve($admin, $complaint);

        $after = app(DashboardService::class)->getDashboardData($admin);
        $pendingAfter = collect($after['cards'])->firstWhere('id', 'pending_approval');

        $this->assertSame(0, $pendingAfter['value']);
    }

    public function test_complaint_appears_in_dashboard_recent_activity(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $this->createComplaint($owner, $this->createAttendance($owner));

        $data = app(DashboardService::class)->getDashboardData($admin);

        $activity = collect($data['activities'])->firstWhere('type', 'complaint');

        $this->assertNotNull($activity);
        $this->assertSame('pending', $activity['status']);
    }

    public function test_role_authorization_cannot_be_bypassed_via_direct_http(): void
    {
        $owner = $this->createUser('user');
        $attacker = $this->createUser('user');
        $complaint = $this->createComplaint($owner, $this->createAttendance($owner));

        $this->actingAs($attacker)
            ->post(route('attendance-complaints.cancel', $complaint))
            ->assertForbidden();
        $this->actingAs($attacker)
            ->post(route('attendance-complaints.approve', $complaint))
            ->assertForbidden();
        $this->actingAs($attacker)
            ->post(route('attendance-complaints.reject', $complaint))
            ->assertForbidden();

        $this->assertSame('pending', $complaint->fresh()->status);
    }

    public function test_service_rejects_modifying_finalized_complaint(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $complaint = $this->createComplaint($owner, $this->createAttendance($owner));

        app(AttendanceComplaintService::class)->approve($admin, $complaint);

        $this->expectException(AttendanceComplaintException::class);

        app(AttendanceComplaintService::class)->cancel($owner, $complaint);
    }

    public function test_policy_rules_are_consistent(): void
    {
        $owner = $this->createUser('user');
        $other = $this->createUser('user');
        $admin = $this->createUser('admin');
        $complaint = $this->createComplaint($owner, $this->createAttendance($owner));

        $this->assertFalse(Gate::forUser($other)->allows('view', $complaint));
        $this->assertTrue(Gate::forUser($owner)->allows('view', $complaint));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $complaint));
        $this->assertTrue(Gate::forUser($owner)->allows('cancel', $complaint));
        $this->assertFalse(Gate::forUser($owner)->allows('approve', $complaint));
        $this->assertTrue(Gate::forUser($admin)->allows('approve', $complaint));
    }

    /**
     * The office created by default in the user factory.
     */
    private function factoryOffice(): Office
    {
        return Office::query()->firstOrCreate(
            ['office_code' => 'JKT001'],
            [
                'office_code' => 'JKT001',
                'office_name' => 'Head Office',
                'city' => 'Jakarta',
                'address' => 'Jakarta',
                'status' => 'active',
            ],
        );
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
    private function createAttendance(User $user, array $overrides = []): Attendance
    {
        $this->attendanceCounter++;

        return Attendance::query()->create([...[
            'user_id' => $user->id,
            'attendance_date' => now()->subDays($this->attendanceCounter)->format('Y-m-d'),
            'check_in_time' => '07:50:00',
            'attendance_status' => 'present',
        ], ...$overrides]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createComplaint(User $user, Attendance $attendance, array $overrides = []): AttendanceComplaint
    {
        return AttendanceComplaint::query()->create([...[
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'complaint_reason' => 'I forgot to check in.',
            'status' => 'pending',
        ], ...$overrides]);
    }
}

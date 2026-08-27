<?php

namespace Tests\Feature\BusinessTrip;

use App\Models\BusinessTrip;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_business_trip(): void
    {
        $user = $this->createUser('user');
        $admin = $this->actingAs($user);

        $response = $admin->post(route('business-trips.store'), [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-03',
            'destination' => 'Bandung Branch',
            'purpose' => 'Client meeting',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('business_trips', [
            'user_id' => $user->id,
            'status' => 'pending',
            'destination' => 'Bandung Branch',
        ]);
    }

    public function test_validation_requires_all_fields(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->post(route('business-trips.store'), []);

        $response->assertSessionHasErrors(['start_date', 'end_date', 'destination', 'purpose']);
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->post(route('business-trips.store'), [
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-01',
            'destination' => 'Bandung',
            'purpose' => 'Meeting',
        ]);

        $response->assertSessionHasErrors('end_date');
    }

    public function test_own_requests_are_visible(): void
    {
        $user = $this->createUser('user');
        $this->createBusinessTrip($user);

        $response = $this->actingAs($user)->get(route('business-trips.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('business-trips/index')
                ->where('businessTrips.total', 1));
    }

    public function test_other_user_requests_are_inaccessible(): void
    {
        $userA = $this->createUser('user');
        $userB = $this->createUser('user');
        $this->createBusinessTrip($userB);

        $response = $this->actingAs($userA)->get(route('business-trips.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('businessTrips.total', 0));
    }

    public function test_user_can_cancel_pending(): void
    {
        $user = $this->createUser('user');
        $trip = $this->createBusinessTrip($user, ['status' => 'pending']);

        $response = $this->actingAs($user)->post(route('business-trips.cancel', $trip->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('business_trips', [
            'id' => $trip->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_user_cannot_cancel_approved(): void
    {
        $user = $this->createUser('user');
        $trip = $this->createBusinessTrip($user, ['status' => 'approved']);

        $response = $this->actingAs($user)->post(route('business-trips.cancel', $trip->id));

        $response->assertForbidden();
    }

    public function test_user_cannot_cancel_rejected(): void
    {
        $user = $this->createUser('user');
        $trip = $this->createBusinessTrip($user, ['status' => 'rejected']);

        $response = $this->actingAs($user)->post(route('business-trips.cancel', $trip->id));

        $response->assertForbidden();
    }

    public function test_user_cannot_approve(): void
    {
        $user = $this->createUser('user');
        $trip = $this->createBusinessTrip($user, ['status' => 'pending']);

        $response = $this->actingAs($user)->post(route('business-trips.approve', $trip->id));

        $response->assertForbidden();
    }

    public function test_user_cannot_reject(): void
    {
        $user = $this->createUser('user');
        $trip = $this->createBusinessTrip($user, ['status' => 'pending']);

        $response = $this->actingAs($user)->post(route('business-trips.reject', $trip->id));

        $response->assertForbidden();
    }

    public function test_admin_can_view_office_requests(): void
    {
        $office = $this->createOffice('JKT001', 'Jakarta');
        $admin = $this->createUser('admin', ['office_id' => $office->id]);
        $user = $this->createUser('user', ['office_id' => $office->id]);
        $this->createBusinessTrip($user);

        $response = $this->actingAs($admin)->get(route('business-trips.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('businessTrips.total', 1));
    }

    public function test_admin_cannot_access_other_office(): void
    {
        $officeA = $this->createOffice('JKT001', 'Jakarta');
        $officeB = $this->createOffice('BDO001', 'Bandung');
        $admin = $this->createUser('admin', ['office_id' => $officeA->id]);
        $user = $this->createUser('user', ['office_id' => $officeB->id]);
        $this->createBusinessTrip($user);

        $response = $this->actingAs($admin)->get(route('business-trips.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('businessTrips.total', 0));
    }

    public function test_admin_can_approve_own_office_pending(): void
    {
        $office = $this->createOffice('JKT001', 'Jakarta');
        $admin = $this->createUser('admin', ['office_id' => $office->id]);
        $user = $this->createUser('user', ['office_id' => $office->id]);
        $trip = $this->createBusinessTrip($user, ['status' => 'pending']);

        $response = $this->actingAs($admin)->post(route('business-trips.approve', $trip->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('business_trips', [
            'id' => $trip->id,
            'status' => 'approved',
            'approved_by' => $admin->id,
        ]);
    }

    public function test_admin_can_reject_own_office_pending(): void
    {
        $office = $this->createOffice('JKT001', 'Jakarta');
        $admin = $this->createUser('admin', ['office_id' => $office->id]);
        $user = $this->createUser('user', ['office_id' => $office->id]);
        $trip = $this->createBusinessTrip($user, ['status' => 'pending']);

        $response = $this->actingAs($admin)->post(route('business-trips.reject', $trip->id), [
            'approval_notes' => 'Not justified',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('business_trips', [
            'id' => $trip->id,
            'status' => 'rejected',
            'approval_notes' => 'Not justified',
        ]);
    }

    public function test_admin_cannot_approve_non_pending(): void
    {
        $office = $this->createOffice('JKT001', 'Jakarta');
        $admin = $this->createUser('admin', ['office_id' => $office->id]);
        $user = $this->createUser('user', ['office_id' => $office->id]);
        $trip = $this->createBusinessTrip($user, ['status' => 'approved']);

        $response = $this->actingAs($admin)->post(route('business-trips.approve', $trip->id));

        $response->assertForbidden();
    }

    public function test_super_admin_can_view_all(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $user = $this->createUser('user');
        $this->createBusinessTrip($user);

        $response = $this->actingAs($superAdmin)->get(route('business-trips.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('businessTrips.total', 1));
    }

    public function test_super_admin_can_approve(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $user = $this->createUser('user');
        $trip = $this->createBusinessTrip($user, ['status' => 'pending']);

        $response = $this->actingAs($superAdmin)->post(route('business-trips.approve', $trip->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('business_trips', [
            'id' => $trip->id,
            'status' => 'approved',
        ]);
    }

    public function test_overlapping_pending_is_rejected(): void
    {
        $user = $this->createUser('user');
        $this->createBusinessTrip($user, [
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->post(route('business-trips.store'), [
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-13',
            'destination' => 'Surabaya',
            'purpose' => 'Training',
        ]);

        $response->assertSessionHas('error');
    }

    public function test_overlapping_approved_is_rejected(): void
    {
        $user = $this->createUser('user');
        $this->createBusinessTrip($user, [
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)->post(route('business-trips.store'), [
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-13',
            'destination' => 'Surabaya',
            'purpose' => 'Training',
        ]);

        $response->assertSessionHas('error');
    }

    public function test_boundary_overlap_start_equals_end_is_rejected(): void
    {
        $user = $this->createUser('user');
        $this->createBusinessTrip($user, [
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)->post(route('business-trips.store'), [
            'start_date' => '2026-09-12',
            'end_date' => '2026-09-14',
            'destination' => 'Surabaya',
            'purpose' => 'Training',
        ]);

        $response->assertSessionHas('error');
    }

    public function test_cancelled_does_not_conflict(): void
    {
        $user = $this->createUser('user');
        $this->createBusinessTrip($user, [
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($user)->post(route('business-trips.store'), [
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-13',
            'destination' => 'Surabaya',
            'purpose' => 'Training',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('business_trips', [
            'user_id' => $user->id,
            'status' => 'pending',
            'destination' => 'Surabaya',
        ]);
    }

    public function test_rejected_does_not_conflict(): void
    {
        $user = $this->createUser('user');
        $this->createBusinessTrip($user, [
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'status' => 'rejected',
        ]);

        $response = $this->actingAs($user)->post(route('business-trips.store'), [
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-13',
            'destination' => 'Surabaya',
            'purpose' => 'Training',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('business_trips', [
            'user_id' => $user->id,
            'status' => 'pending',
            'destination' => 'Surabaya',
        ]);
    }

    public function test_non_overlapping_request_is_allowed(): void
    {
        $user = $this->createUser('user');
        $this->createBusinessTrip($user, [
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)->post(route('business-trips.store'), [
            'start_date' => '2026-09-13',
            'end_date' => '2026-09-15',
            'destination' => 'Surabaya',
            'purpose' => 'Training',
        ]);

        $response->assertRedirect();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('business-trips.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_active_page_renders(): void
    {
        $user = $this->createUser('user');

        $response = $this->actingAs($user)->get(route('business-trips.create'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('business-trips/create'));
    }

    private function createOffice(string $code, string $name): Office
    {
        return Office::query()->create([
            'office_code' => $code,
            'office_name' => $name,
            'city' => 'Jakarta',
            'address' => 'Jalan Contoh No. 1',
            'status' => 'active',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
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
    private function createBusinessTrip(User $user, array $overrides = []): BusinessTrip
    {
        return BusinessTrip::query()->create([...[
            'user_id' => $user->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-03',
            'destination' => 'Jakarta Branch',
            'purpose' => 'Client meeting',
            'status' => 'pending',
        ], ...$overrides]);
    }
}

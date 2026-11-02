<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VerificationDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_moderators_review_identity_and_vehicle_documents(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();
        $vehicle = $this->vehicleFor($user, Vehicle::STATUS_PENDING);

        $identity = VerificationDocument::create([
            'user_id' => $user->id,
            'type' => VerificationDocument::TYPE_NID,
            'file_path' => 'verifications/nid.jpg',
            'status' => VerificationDocument::STATUS_PENDING,
        ]);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/admin/stats')->assertForbidden();
        $this->getJson('/api/v1/admin/verification-documents')->assertForbidden();

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/stats')
            ->assertOk()
            ->assertJsonPath('data.pending_verifications', 1);

        $this->getJson('/api/v1/admin/verification-documents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.phone', $user->phone)
            ->assertJsonPath('data.0.file_url', "/admin/verification-documents/{$identity->id}/file");

        $this->getJson("/api/v1/admin/verification-documents/{$identity->id}/file")->assertNotFound();

        $this->postJson("/api/v1/admin/verification-documents/{$identity->id}/review", [
            'decision' => 'approve',
        ])->assertOk()
            ->assertJsonPath('data.status', VerificationDocument::STATUS_APPROVED);

        $this->assertNotNull($user->fresh()->nid_verified_at);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonFragment(['type' => 'verification_approved']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/vehicles/{$vehicle->id}/review", [
            'decision' => 'reject',
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->postJson("/api/v1/admin/vehicles/{$vehicle->id}/review", [
            'decision' => 'reject',
            'reason' => 'Registration photo is unreadable.',
        ])->assertOk()
            ->assertJsonPath('data.status', Vehicle::STATUS_REJECTED);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'status' => Vehicle::STATUS_REJECTED,
            'rejection_reason' => 'Registration photo is unreadable.',
        ]);
    }

    public function test_admin_trips_and_moderation_activity_are_audited(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/users/{$user->id}/status", [
            'status' => User::STATUS_SUSPENDED,
            'reason' => 'Policy violation',
        ])->assertOk();

        $this->getJson('/api/v1/admin/activity')
            ->assertOk()
            ->assertJsonPath('data.0.event', 'user_status_changed')
            ->assertJsonPath('data.0.causer.id', $admin->id)
            ->assertJsonPath('data.0.subject.id', $user->id);

        $this->getJson('/api/v1/admin/trips')->assertOk();
    }

    public function test_vehicle_documents_are_reviewed_with_reason(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $owner = User::factory()->create();
        $vehicle = $this->vehicleFor($owner, Vehicle::STATUS_PENDING);

        $document = VehicleDocument::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'registration',
            'file_path' => 'vehicles/'.$vehicle->id.'/registration.jpg',
            'status' => VehicleDocument::STATUS_PENDING,
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/vehicle-documents')
            ->assertOk()
            ->assertJsonPath('data.0.vehicle.registration_no', $vehicle->registration_no);

        $this->postJson("/api/v1/admin/vehicle-documents/{$document->id}/review", [
            'decision' => 'reject',
            'reason' => 'Expired document.',
        ])->assertOk()
            ->assertJsonPath('data.status', VehicleDocument::STATUS_REJECTED)
            ->assertJsonPath('data.rejection_reason', 'Expired document.');

        $this->postJson("/api/v1/admin/vehicle-documents/{$document->id}/review", [
            'decision' => 'approve',
        ])->assertOk()
            ->assertJsonPath('data.status', VehicleDocument::STATUS_APPROVED);
    }

    public function test_admin_suspends_users_and_cannot_suspend_self(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create();

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/users/{$admin->id}/status", [
            'status' => 'suspended',
            'reason' => 'Testing self suspension.',
        ])->assertStatus(422);

        $this->postJson("/api/v1/admin/users/{$user->id}/status", [
            'status' => 'suspended',
            'reason' => 'Repeated no-shows.',
        ])->assertOk()
            ->assertJsonPath('data.status', User::STATUS_SUSPENDED);

        $this->assertSame('Repeated no-shows.', $user->fresh()->moderation_note);
        $this->assertSame(User::STATUS_SUSPENDED, $user->fresh()->status);

        $this->postJson("/api/v1/admin/users/{$user->id}/status", [
            'status' => 'active',
        ])->assertOk()->assertJsonPath('data.status', User::STATUS_ACTIVE);

        $this->assertNull($user->fresh()->moderation_note);
    }

    public function test_trip_participants_report_and_moderators_resolve(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('moderator');
        $provider = User::factory()->identityVerified()->create();
        $vehicle = $this->vehicleFor($provider, Vehicle::STATUS_ACTIVE);
        $passenger = User::factory()->create();
        $outsider = User::factory()->create();

        Sanctum::actingAs($provider);
        $offerId = $this->createOffer($vehicle);

        Sanctum::actingAs($passenger);
        $joinId = $this->postJson("/api/v1/ride-offers/{$offerId}/join")->json('data.id');

        Sanctum::actingAs($provider);
        $tripId = $this->postJson("/api/v1/joins/{$joinId}/accept")->json('trip.id');

        Sanctum::actingAs($outsider);
        $this->postJson('/api/v1/reports', [
            'category' => Report::CATEGORY_SAFETY,
            'description' => 'I was not part of this trip.',
            'trip_id' => $tripId,
        ])->assertUnprocessable()->assertJsonValidationErrors('trip_id');

        Sanctum::actingAs($passenger);
        $report = $this->postJson('/api/v1/reports', [
            'category' => Report::CATEGORY_SAFETY,
            'description' => 'The driver was driving dangerously.',
            'reported_user_id' => $provider->id,
            'trip_id' => $tripId,
        ])->assertCreated()
            ->assertJsonPath('data.status', Report::STATUS_OPEN)
            ->json('data');

        $this->getJson('/api/v1/reports')
            ->assertOk()
            ->assertJsonPath('data.0.id', $report['id']);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/reports')
            ->assertOk()
            ->assertJsonPath('data.0.id', $report['id']);

        $this->postJson("/api/v1/admin/reports/{$report['id']}/resolve", [
            'status' => 'resolved',
            'note' => 'Driver coaching completed.',
        ])->assertOk()
            ->assertJsonPath('data.status', Report::STATUS_RESOLVED)
            ->assertJsonPath('data.resolution_note', 'Driver coaching completed.');

        Sanctum::actingAs($passenger);
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonFragment(['type' => 'report_resolved']);
    }

    private function vehicleFor(User $user, string $status): Vehicle
    {
        return Vehicle::create([
            'user_id' => $user->id,
            'type' => Vehicle::TYPE_MOTORCYCLE,
            'make' => 'Bajaj',
            'model' => 'Pulsar',
            'year' => 2021,
            'registration_no' => 'DHAKA-HA-'.fake()->unique()->numerify('##-####'),
            'seat_capacity' => 1,
            'status' => $status,
            'verified_at' => $status === Vehicle::STATUS_ACTIVE ? now() : null,
        ]);
    }

    private function createOffer(Vehicle $vehicle): int
    {
        return $this->postJson('/api/v1/ride-offers', [
            'vehicle_id' => $vehicle->id,
            'origin_lat' => 23.8103,
            'origin_lng' => 90.4125,
            'origin_label' => 'Mirpur 10',
            'dest_lat' => 23.7330,
            'dest_lng' => 90.3950,
            'dest_label' => 'Dhanmondi',
            'depart_at' => now()->addHours(2)->toIso8601String(),
            'seats_total' => 1,
            'cost_total_poisha' => 30000,
            'provider_participates' => true,
        ])->assertCreated()->json('data.id');
    }
}

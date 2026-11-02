<?php

namespace Tests\Feature;

use App\Models\JoinRequest;
use App\Models\RideRequest;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JoinApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_passenger_joins_offer_and_provider_accepts(): void
    {
        [$provider, $vehicle] = $this->provider();
        $passenger = User::factory()->create();
        $other = User::factory()->create();

        Sanctum::actingAs($provider);
        $offerId = $this->createOffer($vehicle);

        Sanctum::actingAs($passenger);
        $joinId = $this->postJson("/api/v1/ride-offers/{$offerId}/join", [
            'seats' => 1,
            'message' => 'I will be at the corner',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/ride-offers/{$offerId}/join", ['seats' => 1])
            ->assertUnprocessable();

        Sanctum::actingAs($provider);

        $this->getJson('/api/v1/joins/incoming')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Sanctum::actingAs($other);
        $this->postJson("/api/v1/joins/{$joinId}/accept")->assertForbidden();

        Sanctum::actingAs($provider);
        $response = $this->postJson("/api/v1/joins/{$joinId}/accept")->assertOk();

        $tripId = $response->json('trip.id');

        $this->assertDatabaseHas('join_requests', [
            'id' => $joinId,
            'status' => JoinRequest::STATUS_ACCEPTED,
        ]);
        $this->assertDatabaseHas('ride_offers', [
            'id' => $offerId,
            'seats_allocated' => 1,
        ]);
        $this->assertDatabaseHas('trips', [
            'id' => $tripId,
            'ride_offer_id' => $offerId,
            'status' => Trip::STATUS_FORMING,
        ]);
        $this->assertDatabaseCount('trip_participants', 2);

        Sanctum::actingAs($passenger);
        $this->getJson('/api/v1/trips')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tripId)
            ->assertJsonPath('data.0.offer.id', $offerId);
    }

    public function test_second_passenger_cannot_be_accepted_when_offer_is_full(): void
    {
        [$provider, $vehicle] = $this->provider();
        $first = User::factory()->create();
        $second = User::factory()->create();

        Sanctum::actingAs($provider);
        $offerId = $this->createOffer($vehicle);

        Sanctum::actingAs($first);
        $firstJoin = $this->postJson("/api/v1/ride-offers/{$offerId}/join")->assertCreated()->json('data.id');

        Sanctum::actingAs($second);
        $secondJoin = $this->postJson("/api/v1/ride-offers/{$offerId}/join")->assertCreated()->json('data.id');

        Sanctum::actingAs($provider);
        $this->postJson("/api/v1/joins/{$firstJoin}/accept")->assertOk();
        $this->postJson("/api/v1/joins/{$secondJoin}/accept")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('join');
    }

    public function test_provider_joins_request_and_creator_accepts(): void
    {
        $passenger = User::factory()->create();
        [$provider, $vehicle] = $this->provider();

        Sanctum::actingAs($passenger);
        $requestId = $this->createRequest(['seats_needed' => 2, 'pool_max_seats' => 4]);

        Sanctum::actingAs($provider);
        $joinId = $this->postJson("/api/v1/ride-requests/{$requestId}/join", [
            'vehicle_id' => $vehicle->id,
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($passenger);
        $response = $this->postJson("/api/v1/joins/{$joinId}/accept")->assertOk();

        $tripId = $response->json('trip.id');

        $this->assertDatabaseHas('ride_requests', [
            'id' => $requestId,
            'status' => RideRequest::STATUS_IN_PROGRESS,
        ]);
        $this->assertDatabaseHas('trips', [
            'id' => $tripId,
            'ride_request_id' => $requestId,
            'pool_capacity' => 4,
            'seats_allocated' => 2,
        ]);
        $this->assertDatabaseHas('trip_participants', [
            'trip_id' => $tripId,
            'user_id' => $passenger->id,
            'role' => 'passenger',
            'seats' => 2,
        ]);
    }

    public function test_unverified_provider_cannot_join_request(): void
    {
        $passenger = User::factory()->create();
        $user = User::factory()->create();
        $vehicle = $this->vehicleFor($user);

        Sanctum::actingAs($passenger);
        $requestId = $this->createRequest();

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/ride-requests/{$requestId}/join", [
            'vehicle_id' => $vehicle->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('join');
    }

    public function test_join_request_can_be_declined_or_withdrawn(): void
    {
        [$provider, $vehicle] = $this->provider();
        $passenger = User::factory()->create();

        Sanctum::actingAs($provider);
        $offerId = $this->createOffer($vehicle);

        Sanctum::actingAs($passenger);
        $joinId = $this->postJson("/api/v1/ride-offers/{$offerId}/join")->assertCreated()->json('data.id');
        $this->postJson("/api/v1/joins/{$joinId}/withdraw")->assertOk();

        $this->assertDatabaseHas('join_requests', [
            'id' => $joinId,
            'status' => JoinRequest::STATUS_WITHDRAWN,
        ]);

        $secondPassenger = User::factory()->create();
        Sanctum::actingAs($secondPassenger);
        $secondJoin = $this->postJson("/api/v1/ride-offers/{$offerId}/join")->assertCreated()->json('data.id');

        Sanctum::actingAs($provider);
        $this->postJson("/api/v1/joins/{$secondJoin}/decline")->assertOk();

        $this->assertDatabaseHas('join_requests', [
            'id' => $secondJoin,
            'status' => JoinRequest::STATUS_DECLINED,
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

    private function createRequest(array $overrides = []): int
    {
        return $this->postJson('/api/v1/ride-requests', [
            'origin_lat' => 23.8103,
            'origin_lng' => 90.4125,
            'origin_label' => 'Mirpur 10',
            'dest_lat' => 23.7330,
            'dest_lng' => 90.3950,
            'dest_label' => 'Dhanmondi',
            'depart_at' => now()->addHours(2)->toIso8601String(),
            'seats_needed' => 1,
            ...$overrides,
        ])->assertCreated()->json('data.id');
    }

    private function provider(): array
    {
        $user = User::factory()->identityVerified()->create();

        return [$user, $this->vehicleFor($user)];
    }

    private function vehicleFor(User $user): Vehicle
    {
        return Vehicle::create([
            'user_id' => $user->id,
            'type' => Vehicle::TYPE_MOTORCYCLE,
            'make' => 'Bajaj',
            'model' => 'Pulsar',
            'year' => 2021,
            'registration_no' => 'DHAKA-HA-'.fake()->unique()->numerify('##-####'),
            'seat_capacity' => 1,
            'status' => Vehicle::STATUS_ACTIVE,
            'verified_at' => now(),
        ]);
    }
}

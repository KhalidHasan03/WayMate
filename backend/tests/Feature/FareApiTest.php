<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Trip;
use App\Models\TripFare;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FareApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_offer_trip_fare_is_split_agreed_and_settled(): void
    {
        [$provider, $vehicle] = $this->provider();
        $passenger = User::factory()->create();

        Sanctum::actingAs($provider);
        $offerId = $this->createOffer($vehicle);
        $tripId = $this->acceptPassenger($offerId, $passenger, $provider);

        Sanctum::actingAs($provider);
        $fare = $this->putJson("/api/v1/trips/{$tripId}/fare", [
            'provider_participates' => true,
            'items' => [
                ['label' => 'Fuel', 'amount_poisha' => 30000, 'split_method' => 'equal'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.status', TripFare::STATUS_AGREEING)
            ->assertJsonPath('data.estimated_total_poisha', 30000)
            ->assertJsonCount(2, 'data.contributions')
            ->json('data');

        $this->assertEqualsCanonicalizing(
            [15000, 15000],
            array_column($fare['contributions'], 'amount_poisha'),
        );

        $this->postJson("/api/v1/trips/{$tripId}/fare/agree")->assertOk();

        Sanctum::actingAs($passenger);
        $this->postJson("/api/v1/trips/{$tripId}/fare/agree")
            ->assertOk()
            ->assertJsonPath('data.status', TripFare::STATUS_AGREED)
            ->assertJsonPath('data.agreed_total_poisha', 30000);

        Sanctum::actingAs($provider);
        $contributions = $this->getJson("/api/v1/trips/{$tripId}/fare")
            ->assertOk()
            ->json('data.contributions');

        foreach ($contributions as $contribution) {
            $this->postJson("/api/v1/trips/{$tripId}/fare/contributions/{$contribution['id']}/settle", [
                'status' => Contribution::STATUS_PAID,
            ])->assertOk();
        }

        $this->assertDatabaseHas('trip_fares', [
            'trip_id' => $tripId,
            'status' => TripFare::STATUS_SETTLED,
        ]);
        $this->assertSame(0, Contribution::query()->where('trip_id', $tripId)
            ->where('status', Contribution::STATUS_PENDING)->count());
    }

    public function test_request_trip_fare_weights_passenger_seats_and_provider(): void
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
        $tripId = $this->postJson("/api/v1/joins/{$joinId}/accept")->assertOk()->json('trip.id');

        $this->putJson("/api/v1/trips/{$tripId}/fare", [
            'provider_participates' => true,
            'items' => [
                ['label' => 'Fuel', 'amount_poisha' => 10000, 'split_method' => 'equal'],
            ],
        ])->assertOk()->assertJsonCount(2, 'data.contributions');

        $this->assertDatabaseHas('contributions', [
            'trip_id' => $tripId,
            'user_id' => $passenger->id,
            'amount_poisha' => 6666,
        ]);
        $this->assertDatabaseHas('contributions', [
            'trip_id' => $tripId,
            'user_id' => $provider->id,
            'amount_poisha' => 3334,
        ]);

        Sanctum::actingAs($provider);
        $this->putJson("/api/v1/trips/{$tripId}/fare", [
            'provider_participates' => true,
            'items' => [
                ['label' => 'Fuel', 'amount_poisha' => 12000, 'split_method' => 'equal'],
            ],
        ])->assertForbidden();
    }

    public function test_trip_lifecycle_start_and_complete(): void
    {
        [$provider, $vehicle] = $this->provider();
        $passenger = User::factory()->create();

        Sanctum::actingAs($provider);
        $offerId = $this->createOffer($vehicle);
        $tripId = $this->acceptPassenger($offerId, $passenger, $provider);

        Sanctum::actingAs($passenger);
        $this->postJson("/api/v1/trips/{$tripId}/start")->assertForbidden();

        Sanctum::actingAs($provider);
        $this->postJson("/api/v1/trips/{$tripId}/start")
            ->assertOk()
            ->assertJsonPath('data.status', Trip::STATUS_IN_PROGRESS);

        $this->postJson("/api/v1/trips/{$tripId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', Trip::STATUS_COMPLETED);

        $this->assertDatabaseHas('ride_offers', [
            'id' => $offerId,
            'status' => 'completed',
        ]);
        $this->assertSame(1, User::query()->whereKey($passenger->id)->value('trips_completed'));
        $this->assertSame(1, User::query()->whereKey($provider->id)->value('trips_completed'));
    }

    public function test_fare_adjustment_is_voted_and_applied(): void
    {
        [$provider, $vehicle] = $this->provider();
        $passenger = User::factory()->create();

        Sanctum::actingAs($provider);
        $offerId = $this->createOffer($vehicle);
        $tripId = $this->acceptPassenger($offerId, $passenger, $provider);
        $this->setAndAgreeFare($tripId, $provider, $passenger, 30000);

        Sanctum::actingAs($passenger);
        $response = $this->postJson("/api/v1/trips/{$tripId}/fare/adjustments", [
            'new_total_poisha' => 40000,
            'reason' => 'Extra bridge toll',
        ])->assertOk()
            ->assertJsonPath('data.status', TripFare::STATUS_ADJUSTMENT_PENDING)
            ->assertJsonPath('data.adjustments.0.new_total_poisha', 40000)
            ->assertJsonPath('data.adjustments.0.status', 'pending');

        $adjustmentId = $response->json('data.adjustments.0.id');

        $this->postJson("/api/v1/trips/{$tripId}/fare/adjustments/{$adjustmentId}/vote", [
            'vote' => 'accept',
        ])->assertUnprocessable();

        Sanctum::actingAs($provider);
        $response = $this->postJson("/api/v1/trips/{$tripId}/fare/adjustments/{$adjustmentId}/vote", [
            'vote' => 'accept',
        ])->assertOk()
            ->assertJsonPath('data.status', TripFare::STATUS_AGREED)
            ->assertJsonPath('data.fare_version', 2)
            ->assertJsonPath('data.agreed_total_poisha', 40000)
            ->assertJsonCount(2, 'data.contributions');

        $this->assertEqualsCanonicalizing(
            [20000, 20000],
            array_column($response->json('data.contributions'), 'amount_poisha'),
        );

        $this->assertDatabaseHas('fare_adjustments', [
            'id' => $adjustmentId,
            'status' => 'accepted',
        ]);
    }

    public function test_fare_adjustment_can_be_rejected(): void
    {
        [$provider, $vehicle] = $this->provider();
        $passenger = User::factory()->create();

        Sanctum::actingAs($provider);
        $offerId = $this->createOffer($vehicle);
        $tripId = $this->acceptPassenger($offerId, $passenger, $provider);
        $this->setAndAgreeFare($tripId, $provider, $passenger, 30000);

        Sanctum::actingAs($passenger);
        $adjustmentId = $this->postJson("/api/v1/trips/{$tripId}/fare/adjustments", [
            'new_total_poisha' => 50000,
        ])->assertOk()->json('data.adjustments.0.id');

        Sanctum::actingAs($provider);
        $this->postJson("/api/v1/trips/{$tripId}/fare/adjustments/{$adjustmentId}/vote", [
            'vote' => 'reject',
        ])->assertOk()
            ->assertJsonPath('data.status', TripFare::STATUS_AGREED)
            ->assertJsonPath('data.fare_version', 1)
            ->assertJsonPath('data.agreed_total_poisha', 30000);

        $this->assertDatabaseHas('fare_adjustments', [
            'id' => $adjustmentId,
            'status' => 'rejected',
        ]);
    }

    private function setAndAgreeFare(int $tripId, User $provider, User $passenger, int $total): void
    {
        Sanctum::actingAs($provider);

        $this->putJson("/api/v1/trips/{$tripId}/fare", [
            'provider_participates' => true,
            'items' => [
                ['label' => 'Fuel', 'amount_poisha' => $total, 'split_method' => 'equal'],
            ],
        ])->assertOk();

        $this->postJson("/api/v1/trips/{$tripId}/fare/agree")->assertOk();

        Sanctum::actingAs($passenger);
        $this->postJson("/api/v1/trips/{$tripId}/fare/agree")
            ->assertOk()
            ->assertJsonPath('data.status', TripFare::STATUS_AGREED);
    }

    private function acceptPassenger(int $offerId, User $passenger, User $provider): int
    {
        Sanctum::actingAs($passenger);

        $joinId = $this->postJson("/api/v1/ride-offers/{$offerId}/join")
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($provider);

        return $this->postJson("/api/v1/joins/{$joinId}/accept")
            ->assertOk()
            ->json('trip.id');
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

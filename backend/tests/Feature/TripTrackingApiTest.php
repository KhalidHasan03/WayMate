<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripTrackingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_driver_can_broadcast_and_passengers_can_follow_trip_location(): void
    {
        [$provider, $vehicle] = $this->provider();
        $passenger = User::factory()->create();

        Sanctum::actingAs($provider);
        $offerId = $this->postJson('/api/v1/ride-offers', [
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

        Sanctum::actingAs($passenger);
        $joinId = $this->postJson("/api/v1/ride-offers/{$offerId}/join")->assertCreated()->json('data.id');

        Sanctum::actingAs($provider);
        $tripId = $this->postJson("/api/v1/joins/{$joinId}/accept")->assertOk()->json('trip.id');

        $this->postJson("/api/v1/trips/{$tripId}/start")->assertOk();

        $this->postJson("/api/v1/trips/{$tripId}/tracking/start")
            ->assertOk()
            ->assertJsonPath('data.tracking_enabled', true);

        $this->postJson("/api/v1/trips/{$tripId}/tracking/points", [
            'lat' => 23.8060,
            'lng' => 90.4100,
            'accuracy_m' => 8.5,
            'recorded_at' => now()->toIso8601String(),
        ])->assertCreated()
            ->assertJsonPath('data.lat', 23.806)
            ->assertJsonPath('data.accuracy_m', 8.5);

        Sanctum::actingAs($passenger);
        $this->getJson("/api/v1/trips/{$tripId}/tracking")
            ->assertOk()
            ->assertJsonPath('data.tracking_enabled', true)
            ->assertJsonCount(1, 'data.locations')
            ->assertJsonPath('data.locations.0.lat', 23.806);

        $this->postJson("/api/v1/trips/{$tripId}/tracking/points", [
            'lat' => 23.8050,
            'lng' => 90.4090,
        ])->assertForbidden();

        Sanctum::actingAs($provider);
        $this->getJson("/api/v1/trips/{$tripId}/events")
            ->assertOk()
            ->assertJsonFragment(['event' => 'trip_started']);

        $this->postJson("/api/v1/trips/{$tripId}/complete")->assertOk();

        $this->assertDatabaseHas('trips', [
            'id' => $tripId,
            'tracking_enabled' => false,
        ]);
    }

    private function provider(): array
    {
        $user = User::factory()->identityVerified()->create();

        $vehicle = Vehicle::create([
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

        return [$user, $vehicle];
    }
}

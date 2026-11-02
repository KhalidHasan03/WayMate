<?php

namespace Tests\Feature;

use App\Models\RideOffer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RideOfferApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_verified_provider_can_create_update_and_cancel_offer(): void
    {
        [$user, $vehicle] = $this->provider();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/ride-offers', $this->payload([
            'vehicle_id' => $vehicle->id,
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.seats_available', 1)
            ->assertJsonPath('data.provider_participates', true);

        $id = $response->json('data.id');

        $this->getJson('/api/v1/ride-offers')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->patchJson('/api/v1/ride-offers/'.$id, ['notes' => 'Leaving from the mosque'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Leaving from the mosque');

        $this->postJson('/api/v1/ride-offers/'.$id.'/cancel')->assertOk();

        $this->assertDatabaseHas('ride_offers', [
            'id' => $id,
            'status' => RideOffer::STATUS_CANCELLED,
        ]);
    }

    public function test_unverified_provider_is_blocked(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicleFor($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/ride-offers', $this->payload([
            'vehicle_id' => $vehicle->id,
        ]))->assertForbidden();
    }

    public function test_inactive_vehicle_is_rejected(): void
    {
        [$user, $vehicle] = $this->provider();
        $vehicle->update(['status' => Vehicle::STATUS_PENDING]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/ride-offers', $this->payload([
            'vehicle_id' => $vehicle->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
    }

    public function test_seats_cannot_exceed_vehicle_capacity(): void
    {
        [$user, $vehicle] = $this->provider();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/ride-offers', $this->payload([
            'vehicle_id' => $vehicle->id,
            'seats_total' => 2,
        ]))->assertUnprocessable()->assertJsonValidationErrors('seats_total');
    }

    public function test_other_users_cannot_edit_offer(): void
    {
        [$user, $vehicle] = $this->provider();
        $offer = RideOffer::create([
            ...$this->payload(['vehicle_id' => $vehicle->id]),
            'provider_id' => $user->id,
            'status' => RideOffer::STATUS_OPEN,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/v1/ride-offers/'.$offer->id, ['notes' => 'Hijack'])->assertForbidden();
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

    private function payload(array $overrides = []): array
    {
        return [
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
            ...$overrides,
        ];
    }
}

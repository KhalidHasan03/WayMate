<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RideSearchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_search_finds_open_offers_without_creating_a_request(): void
    {
        [$provider, $vehicle] = $this->provider();
        Sanctum::actingAs($provider);
        $offerId = $this->createOffer($vehicle);

        $passenger = User::factory()->create();
        Sanctum::actingAs($passenger);

        $this->getJson('/api/v1/ride-offers/search?'.http_build_query([
            'origin_lat' => 23.8103,
            'origin_lng' => 90.4125,
            'dest_lat' => 23.7330,
            'dest_lng' => 90.3950,
            'depart_at' => now()->addHours(2)->toIso8601String(),
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.offer.id', $offerId)
            ->assertJsonPath('data.0.offer.provider.identity_verified', true)
            ->assertJsonPath('data.0.match.reasons.0', 'Pickup is on the route');

        $this->assertDatabaseCount('ride_requests', 0);
    }

    public function test_search_excludes_own_offers_and_respects_women_only(): void
    {
        [$provider, $vehicle] = $this->provider('female');
        Sanctum::actingAs($provider);
        $this->createOffer($vehicle, ['women_only' => true]);

        $query = [
            'origin_lat' => 23.8103,
            'origin_lng' => 90.4125,
            'dest_lat' => 23.7330,
            'dest_lng' => 90.3950,
            'depart_at' => now()->addHours(2)->toIso8601String(),
        ];

        $this->getJson('/api/v1/ride-offers/search?'.http_build_query($query))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $male = User::factory()->create(['gender' => 'male']);
        Sanctum::actingAs($male);

        $this->getJson('/api/v1/ride-offers/search?'.http_build_query($query))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $query['women_only'] = 1;
        $female = User::factory()->create(['gender' => 'female']);
        Sanctum::actingAs($female);

        $this->getJson('/api/v1/ride-offers/search?'.http_build_query($query))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    private function createOffer(Vehicle $vehicle, array $overrides = []): int
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
            'seats_total' => 2,
            'cost_total_poisha' => 30000,
            'provider_participates' => true,
            ...$overrides,
        ])->assertCreated()->json('data.id');
    }

    private function provider(string $gender = 'male'): array
    {
        $user = User::factory()->identityVerified()->create(['gender' => $gender]);

        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'type' => Vehicle::TYPE_CAR,
            'make' => 'Toyota',
            'model' => 'Axio',
            'year' => 2021,
            'registration_no' => 'DHAKA-GA-'.fake()->unique()->numerify('##-####'),
            'seat_capacity' => 3,
            'status' => Vehicle::STATUS_ACTIVE,
            'verified_at' => now(),
        ]);

        return [$user, $vehicle];
    }
}

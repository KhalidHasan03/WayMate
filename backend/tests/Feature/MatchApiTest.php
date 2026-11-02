<?php

namespace Tests\Feature;

use App\Models\RideOffer;
use App\Models\RideRequest;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MatchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_request_finds_offers_on_the_same_corridor(): void
    {
        $passenger = User::factory()->create();
        $requestId = $this->createRequest($passenger);

        [$provider, $vehicle] = $this->provider();
        Sanctum::actingAs($provider);

        $offerId = $this->postJson('/api/v1/ride-offers', $this->offerPayload([
            'vehicle_id' => $vehicle->id,
        ]))->assertCreated()->json('data.id');

        Sanctum::actingAs($passenger);

        $this->getJson("/api/v1/ride-requests/{$requestId}/matches")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.offer.id', $offerId)
            ->assertJsonPath('data.0.estimated_share_poisha', 15000)
            ->assertJsonPath('data.0.estimated_total_poisha', 15000)
            ->assertJsonPath('data.0.match.score', 100);

        Sanctum::actingAs($provider);

        $this->getJson("/api/v1/ride-offers/{$offerId}/matches")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.request.id', $requestId);
    }

    public function test_utc_departure_input_matches_in_the_app_timezone(): void
    {
        $departAt = now()->addHours(2)->utc()->toIso8601String();
        $passenger = User::factory()->create();
        $requestId = $this->createRequest($passenger, ['depart_at' => $departAt]);

        $stored = RideRequest::query()->findOrFail($requestId)->depart_at;
        $this->assertTrue($stored->equalTo(Carbon::parse($departAt)));

        [$provider, $vehicle] = $this->provider();
        Sanctum::actingAs($provider);

        $offerId = $this->postJson('/api/v1/ride-offers', $this->offerPayload([
            'vehicle_id' => $vehicle->id,
            'depart_at' => $departAt,
        ]))->assertCreated()->json('data.id');

        Sanctum::actingAs($passenger);

        $this->getJson("/api/v1/ride-requests/{$requestId}/matches")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.offer.id', $offerId);

        Sanctum::actingAs($provider);

        $this->getJson("/api/v1/ride-offers/{$offerId}/matches")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.request.id', $requestId);
    }

    public function test_reverse_direction_does_not_match(): void
    {
        $passenger = User::factory()->create();
        $requestId = $this->createRequest($passenger);

        [$provider, $vehicle] = $this->provider();
        Sanctum::actingAs($provider);

        $this->postJson('/api/v1/ride-offers', $this->offerPayload([
            'vehicle_id' => $vehicle->id,
            'origin_lat' => $this->destLat,
            'origin_lng' => $this->destLng,
            'dest_lat' => $this->originLat,
            'dest_lng' => $this->originLng,
        ]))->assertCreated();

        Sanctum::actingAs($passenger);

        $this->getJson("/api/v1/ride-requests/{$requestId}/matches")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_transport_preference_and_seat_availability_are_respected(): void
    {
        $passenger = User::factory()->create();
        $requestId = $this->createRequest($passenger, ['transport_preference' => 'private']);

        [$provider, $vehicle] = $this->provider();

        $offer = RideOffer::create([
            ...$this->offerPayload([
                'vehicle_id' => $vehicle->id,
                'origin_lat' => $this->originLat,
                'origin_lng' => $this->originLng,
                'dest_lat' => $this->destLat,
                'dest_lng' => $this->destLng,
            ]),
            'provider_id' => $provider->id,
            'route_path' => [$this->pathPoint(0), $this->pathPoint(1)],
            'status' => RideOffer::STATUS_OPEN,
            'seats_allocated' => 0,
            'expires_at' => now()->addDays(1),
        ]);

        Sanctum::actingAs($passenger);

        $this->getJson("/api/v1/ride-requests/{$requestId}/matches")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $offer->update(['seats_allocated' => 1]);

        $car = Vehicle::create([
            'user_id' => $provider->id,
            'type' => Vehicle::TYPE_CAR,
            'make' => 'Toyota',
            'model' => 'Axio',
            'registration_no' => 'DHAKA-CAR-'.fake()->unique()->numerify('####'),
            'seat_capacity' => 3,
            'status' => Vehicle::STATUS_ACTIVE,
            'verified_at' => now(),
        ]);

        $offer->update(['vehicle_id' => $car->id, 'seats_total' => 2, 'seats_allocated' => 1]);

        $this->getJson("/api/v1/ride-requests/{$requestId}/matches")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_women_only_requests_only_match_women_drivers(): void
    {
        $femalePassenger = User::factory()->create(['gender' => 'female']);
        $requestId = $this->createRequest($femalePassenger, ['women_only' => true]);

        [$maleProvider, $motorcycle] = $this->provider();

        $this->createOfferDirect($maleProvider, $motorcycle);

        Sanctum::actingAs($femalePassenger);

        $this->getJson("/api/v1/ride-requests/{$requestId}/matches")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        [$femaleProvider, $femaleVehicle] = $this->provider('female');
        $this->createOfferDirect($femaleProvider, $femaleVehicle);

        $this->getJson("/api/v1/ride-requests/{$requestId}/matches")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    private float $originLat = 23.8103;

    private float $originLng = 90.4125;

    private float $destLat = 23.7330;

    private float $destLng = 90.3950;

    private function createRequest(User $user, array $overrides = []): int
    {
        Sanctum::actingAs($user);

        return $this->postJson('/api/v1/ride-requests', [
            'origin_lat' => $this->originLat,
            'origin_lng' => $this->originLng,
            'origin_label' => 'Mirpur 10',
            'dest_lat' => $this->destLat,
            'dest_lng' => $this->destLng,
            'dest_label' => 'Dhanmondi',
            'depart_at' => now()->addHours(2)->toIso8601String(),
            'seats_needed' => 1,
            ...$overrides,
        ])->assertCreated()->json('data.id');
    }

    private function createOfferDirect(User $provider, Vehicle $vehicle): RideOffer
    {
        return RideOffer::create([
            'provider_id' => $provider->id,
            'vehicle_id' => $vehicle->id,
            'origin_lat' => $this->originLat,
            'origin_lng' => $this->originLng,
            'dest_lat' => $this->destLat,
            'dest_lng' => $this->destLng,
            'origin_label' => 'Mirpur 10',
            'dest_label' => 'Dhanmondi',
            'origin_geohash' => 'w5',
            'dest_geohash' => 'w5',
            'route_path' => [$this->pathPoint(0), $this->pathPoint(1)],
            'distance_m' => 9000,
            'duration_s' => 1200,
            'depart_at' => now()->addHours(2),
            'depart_window_min' => 60,
            'seats_total' => 1,
            'seats_allocated' => 0,
            'cost_total_poisha' => 30000,
            'provider_participates' => true,
            'women_only' => true,
            'status' => RideOffer::STATUS_OPEN,
            'expires_at' => now()->addDays(1),
        ]);
    }

    private function pathPoint(int $index): array
    {
        return $index === 0
            ? [$this->originLat, $this->originLng]
            : [$this->destLat, $this->destLng];
    }

    private function provider(string $gender = 'male'): array
    {
        $user = User::factory()->identityVerified()->create(['gender' => $gender]);

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

    private function offerPayload(array $overrides = []): array
    {
        return [
            'origin_lat' => $this->originLat,
            'origin_lng' => $this->originLng,
            'origin_label' => 'Mirpur 10',
            'dest_lat' => $this->destLat,
            'dest_lng' => $this->destLng,
            'dest_label' => 'Dhanmondi',
            'depart_at' => now()->addHours(2)->toIso8601String(),
            'seats_total' => 1,
            'cost_total_poisha' => 30000,
            'provider_participates' => true,
            ...$overrides,
        ];
    }
}

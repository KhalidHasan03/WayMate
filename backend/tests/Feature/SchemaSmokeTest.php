<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\FareItem;
use App\Models\JoinRequest;
use App\Models\RideOffer;
use App\Models\RideRequest;
use App\Models\Trip;
use App\Models\TripFare;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_core_ride_lifecycle_schema_works(): void
    {
        $creator = User::factory()->withProfile()->create();
        $creator->assignRole('user');

        $vehicle = Vehicle::create([
            'user_id' => $creator->id,
            'type' => Vehicle::TYPE_MOTORCYCLE,
            'make' => 'Bajaj',
            'model' => 'Pulsar',
            'year' => 2021,
            'registration_no' => 'DHAKA-HA-11-1234',
            'seat_capacity' => 1,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        $request = RideRequest::create([
            'creator_id' => $creator->id,
            'origin_lat' => 23.8103,
            'origin_lng' => 90.4125,
            'origin_label' => 'Mirpur 10',
            'dest_lat' => 23.7330,
            'dest_lng' => 90.3950,
            'dest_label' => 'Dhanmondi',
            'depart_at' => now()->addHours(2),
            'seats_needed' => 1,
            'pool_max_seats' => 3,
            'status' => RideRequest::STATUS_OPEN,
        ]);

        $offer = RideOffer::create([
            'provider_id' => $creator->id,
            'vehicle_id' => $vehicle->id,
            'origin_lat' => 23.8103,
            'origin_lng' => 90.4125,
            'origin_label' => 'Mirpur 10',
            'dest_lat' => 23.7330,
            'dest_lng' => 90.3950,
            'dest_label' => 'Dhanmondi',
            'depart_at' => now()->addHours(2),
            'seats_total' => 2,
            'seats_allocated' => 0,
            'cost_total_poisha' => 30000,
            'status' => RideOffer::STATUS_OPEN,
        ]);

        $this->assertTrue($request->isOpen());
        $this->assertSame(2, $offer->seatsAvailable());

        $join = JoinRequest::create([
            'ride_request_id' => $request->id,
            'user_id' => $creator->id,
            'role' => JoinRequest::ROLE_PASSENGER,
            'seats' => 1,
            'pair_key' => str_pad('pair-1', 41, 'x'),
            'service_date' => now()->toDateString(),
            'status' => JoinRequest::STATUS_ACCEPTED,
            'decided_by' => $creator->id,
            'decided_at' => now(),
        ]);

        $trip = Trip::create([
            'ride_request_id' => $request->id,
            'coordinator_id' => $creator->id,
            'transport_mode' => Trip::MODE_UNDECIDED,
            'pool_capacity' => 3,
            'seats_allocated' => 1,
            'status' => Trip::STATUS_FORMING,
        ]);

        $trip->participants()->create([
            'user_id' => $creator->id,
            'role' => 'passenger',
            'seats' => 1,
            'status' => 'consent_pending',
        ]);

        $fare = TripFare::create([
            'trip_id' => $trip->id,
            'estimated_total_poisha' => 30000,
            'participant_count' => 1,
            'provider_participates' => true,
            'collector_id' => $creator->id,
            'status' => TripFare::STATUS_AGREEING,
        ]);

        $item = FareItem::create([
            'trip_fare_id' => $fare->id,
            'label' => 'Fuel',
            'amount_poisha' => 30000,
            'split_method' => FareItem::SPLIT_EQUAL,
        ]);

        Contribution::create([
            'trip_id' => $trip->id,
            'user_id' => $creator->id,
            'fare_version' => 1,
            'fare_item_id' => $item->id,
            'kind' => Contribution::KIND_SHARE,
            'amount_poisha' => 15000,
            'status' => Contribution::STATUS_PENDING,
        ]);

        $this->assertSame($join->id, $request->joinRequests()->first()->id);
        $this->assertCount(1, $trip->participants);
        $this->assertSame($trip->id, $fare->trip->id);
        $this->assertSame(30000, (int) $fare->items()->sum('amount_poisha'));
    }

    public function test_only_one_active_trip_per_request(): void
    {
        $user = User::factory()->create();
        $request = RideRequest::create([
            'creator_id' => $user->id,
            'origin_lat' => 23.8,
            'origin_lng' => 90.4,
            'origin_label' => 'A',
            'dest_lat' => 23.7,
            'dest_lng' => 90.3,
            'dest_label' => 'B',
            'depart_at' => now()->addHour(),
            'seats_needed' => 1,
            'pool_max_seats' => 2,
            'status' => RideRequest::STATUS_OPEN,
        ]);

        Trip::create([
            'ride_request_id' => $request->id,
            'coordinator_id' => $user->id,
            'pool_capacity' => 2,
            'seats_allocated' => 1,
            'status' => Trip::STATUS_FORMING,
        ]);

        $this->expectException(QueryException::class);

        Trip::create([
            'ride_request_id' => $request->id,
            'coordinator_id' => $user->id,
            'pool_capacity' => 2,
            'seats_allocated' => 1,
            'status' => Trip::STATUS_FORMING,
        ]);
    }

    public function test_seat_allocation_cannot_exceed_capacity(): void
    {
        $this->expectException(QueryException::class);

        $user = User::factory()->create();

        $request = RideRequest::create([
            'creator_id' => $user->id,
            'origin_lat' => 23.8,
            'origin_lng' => 90.4,
            'origin_label' => 'A',
            'dest_lat' => 23.7,
            'dest_lng' => 90.3,
            'dest_label' => 'B',
            'depart_at' => now()->addHour(),
            'seats_needed' => 2,
            'pool_max_seats' => 3,
            'status' => RideRequest::STATUS_OPEN,
        ]);

        Trip::create([
            'ride_request_id' => $request->id,
            'coordinator_id' => $user->id,
            'pool_capacity' => 1,
            'seats_allocated' => 2,
            'status' => Trip::STATUS_FORMING,
        ]);
    }
}

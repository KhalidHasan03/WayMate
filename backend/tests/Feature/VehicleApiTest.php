<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_vehicle_crud_and_review_reset(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/vehicles', [
            'type' => 'motorcycle',
            'make' => 'Bajaj',
            'model' => 'Pulsar 150',
            'year' => 2021,
            'registration_no' => 'DHAKA-HA-11-1234',
            'color' => 'Black',
            'seat_capacity' => 1,
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'pending');

        $vehicle = Vehicle::query()->firstOrFail();

        $this->getJson('/api/v1/vehicles')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/vehicles/'.$vehicle->id)
            ->assertOk()
            ->assertJsonPath('data.registration_no', 'DHAKA-HA-11-1234');

        $vehicle->update(['status' => Vehicle::STATUS_ACTIVE, 'verified_at' => now()]);

        $this->patchJson('/api/v1/vehicles/'.$vehicle->id, ['year' => 2022])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->deleteJson('/api/v1/vehicles/'.$vehicle->id)->assertOk();
        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }

    public function test_other_users_cannot_view_or_edit_vehicle(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $vehicle = Vehicle::create([
            'user_id' => $owner->id,
            'type' => 'car',
            'make' => 'Toyota',
            'model' => 'Corolla',
            'registration_no' => 'DHAKA-GA-22-0001',
            'seat_capacity' => 4,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($other);

        $this->getJson('/api/v1/vehicles/'.$vehicle->id)->assertForbidden();
        $this->patchJson('/api/v1/vehicles/'.$vehicle->id, ['color' => 'Red'])->assertForbidden();
        $this->deleteJson('/api/v1/vehicles/'.$vehicle->id)->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/vehicles')->assertUnauthorized();
    }
}

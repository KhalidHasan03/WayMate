<?php

namespace Tests\Feature;

use App\Models\JoinRequest;
use App\Models\RideRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RideRequestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_can_create_list_show_update_and_cancel_request(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/ride-requests', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.seats_needed', 1)
            ->assertJsonPath('data.pool_max_seats', 3);

        $id = $response->json('data.id');

        $this->getJson('/api/v1/ride-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/ride-requests/'.$id)
            ->assertOk()
            ->assertJsonPath('data.origin.label', 'Mirpur 10');

        $this->patchJson('/api/v1/ride-requests/'.$id, [
            'notes' => 'Beside the roundabout',
            'seats_needed' => 2,
            'pool_max_seats' => 3,
        ])->assertOk()
            ->assertJsonPath('data.notes', 'Beside the roundabout')
            ->assertJsonPath('data.seats_needed', 2);

        $request = RideRequest::query()->findOrFail($id);
        $request->joinRequests()->create([
            'user_id' => User::factory()->create()->id,
            'role' => JoinRequest::ROLE_PASSENGER,
            'seats' => 1,
            'pair_key' => str_pad('pair-cancel', 41, 'x'),
            'service_date' => now()->toDateString(),
            'status' => JoinRequest::STATUS_PENDING,
        ]);

        $this->postJson('/api/v1/ride-requests/'.$id.'/cancel')->assertOk();

        $this->assertDatabaseHas('ride_requests', [
            'id' => $id,
            'status' => RideRequest::STATUS_CANCELLED,
        ]);
        $this->assertDatabaseHas('join_requests', [
            'ride_request_id' => $id,
            'status' => JoinRequest::STATUS_CANCELLED,
        ]);
    }

    public function test_lead_time_window_is_enforced(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/ride-requests', $this->payload([
            'depart_at' => now()->addMinutes(5)->toIso8601String(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('depart_at');

        $this->postJson('/api/v1/ride-requests', $this->payload([
            'depart_at' => now()->addDays(8)->toIso8601String(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('depart_at');
    }

    public function test_women_only_request_requires_female_creator(): void
    {
        Sanctum::actingAs(User::factory()->create(['gender' => 'male']));

        $this->postJson('/api/v1/ride-requests', $this->payload(['women_only' => true]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('women_only');
    }

    public function test_other_users_cannot_view_request(): void
    {
        $owner = User::factory()->create();
        $request = RideRequest::create([
            ...$this->payload(),
            'creator_id' => $owner->id,
            'status' => RideRequest::STATUS_OPEN,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/ride-requests/'.$request->id)->assertForbidden();
    }

    public function test_seats_cannot_exceed_pool_capacity(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/ride-requests', $this->payload([
            'seats_needed' => 4,
            'pool_max_seats' => 2,
        ]))->assertUnprocessable()->assertJsonValidationErrors('pool_max_seats');
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
            'seats_needed' => 1,
            'pool_max_seats' => 3,
            'accepting_copassengers' => true,
            'transport_preference' => 'any',
            ...$overrides,
        ];
    }
}

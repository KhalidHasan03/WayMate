<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_join_and_chat_events_create_readable_notifications(): void
    {
        [$provider, $vehicle] = $this->provider();
        $passenger = User::factory()->create();
        $outsider = User::factory()->create();

        Sanctum::actingAs($provider);
        $offerId = $this->createOffer($vehicle);

        Sanctum::actingAs($passenger);
        $joinId = $this->postJson("/api/v1/ride-offers/{$offerId}/join")
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($provider);
        $notification = $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('data.0.type', 'join_request')
            ->assertJsonPath('data.0.url', '/app/trips')
            ->json('data.0');

        $this->postJson("/api/v1/joins/{$joinId}/accept")->assertOk();

        Sanctum::actingAs($passenger);
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.type', 'join_accepted');

        $this->postJson("/api/v1/notifications/{$notification['id']}/read")
            ->assertNotFound();

        $passengerNotification = $this->getJson('/api/v1/notifications')->json('data.0');

        $this->postJson("/api/v1/notifications/{$passengerNotification['id']}/read")
            ->assertOk()
            ->assertJsonPath('data.type', 'join_accepted');

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 0);

        Sanctum::actingAs($provider);
        $tripId = $this->getJson('/api/v1/trips')->json('data.0.id');
        $conversationId = $this->getJson("/api/v1/trips/{$tripId}/conversation")->json('data.id');

        $this->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'body' => 'Leaving in five minutes.',
        ])->assertCreated();

        Sanctum::actingAs($passenger);
        $notifications = $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1)
            ->json('data');

        $chatNotification = collect($notifications)->firstWhere('type', 'chat_message');

        $this->assertNotNull($chatNotification);
        $this->assertSame("/app/chat/{$tripId}", $chatNotification['url']);
        $this->assertSame('Leaving in five minutes.', $chatNotification['body']);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 0);

        Sanctum::actingAs($outsider);
        $this->postJson("/api/v1/notifications/{$notification['id']}/read")->assertNotFound();
        $this->deleteJson("/api/v1/notifications/{$notification['id']}")->assertNotFound();
    }

    public function test_device_tokens_are_registered_and_removed(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/device-tokens', [
            'token' => 'web-device-token-1',
            'platform' => 'web',
        ])->assertCreated()->assertJsonPath('data.platform', 'web');

        $this->postJson('/api/v1/device-tokens', [
            'token' => 'web-device-token-1',
            'platform' => 'web',
        ])->assertOk();

        $this->assertDatabaseCount('device_tokens', 1);

        $this->postJson('/api/v1/device-tokens', [
            'platform' => 'web',
        ])->assertUnprocessable()->assertJsonValidationErrors('token');

        $this->deleteJson('/api/v1/device-tokens/web-device-token-1')->assertOk();

        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_trip_cancellation_notifies_other_participants(): void
    {
        [$provider, $vehicle] = $this->provider();
        $passenger = User::factory()->create();

        Sanctum::actingAs($provider);
        $offerId = $this->createOffer($vehicle);

        Sanctum::actingAs($passenger);
        $joinId = $this->postJson("/api/v1/ride-offers/{$offerId}/join")->json('data.id');

        Sanctum::actingAs($provider);
        $tripId = $this->postJson("/api/v1/joins/{$joinId}/accept")->json('trip.id');

        $this->postJson("/api/v1/trips/{$tripId}/cancel", ['reason' => 'Vehicle trouble'])
            ->assertOk();

        Sanctum::actingAs($passenger);
        $notifications = $this->getJson('/api/v1/notifications')->assertOk()->json('data');
        $cancelled = collect($notifications)->firstWhere('type', 'trip_cancelled');

        $this->assertNotNull($cancelled);
        $this->assertSame('The shared trip was cancelled: Vehicle trouble', $cancelled['body']);
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

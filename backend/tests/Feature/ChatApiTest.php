<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_trip_members_chat_and_unread_counts(): void
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
        $tripId = $this->postJson("/api/v1/joins/{$joinId}/accept")
            ->assertOk()
            ->json('trip.id');

        $conversation = $this->getJson("/api/v1/trips/{$tripId}/conversation")
            ->assertOk()
            ->assertJsonCount(2, 'data.participants')
            ->json('data');

        $conversationId = $conversation['id'];

        Sanctum::actingAs($passenger);
        $this->getJson("/api/v1/trips/{$tripId}/conversation")
            ->assertOk()
            ->assertJsonPath('data.id', $conversationId);

        Sanctum::actingAs($provider);
        $message = $this->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'body' => 'I will wait near gate 2.',
        ])->assertCreated()
            ->assertJsonPath('data.body', 'I will wait near gate 2.')
            ->json('data');

        Sanctum::actingAs($passenger);
        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conversationId)
            ->assertJsonPath('data.0.unread_count', 1)
            ->assertJsonPath('data.0.latest_message.body', 'I will wait near gate 2.');

        $this->getJson("/api/v1/conversations/{$conversationId}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $message['id'])
            ->assertJsonPath('data.0.mine', false);

        $this->postJson("/api/v1/conversations/{$conversationId}/read", [
            'last_message_id' => $message['id'],
        ])->assertOk()->assertJsonPath('data.unread_count', 0);

        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.unread_count', 0);

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/conversations/{$conversationId}/messages")->assertForbidden();
        $this->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'body' => 'Hello?',
        ])->assertForbidden();
    }

    public function test_message_validation_and_after_id_polling(): void
    {
        [$provider, $vehicle] = $this->provider();
        $passenger = User::factory()->create();

        Sanctum::actingAs($provider);
        $offerId = $this->createOffer($vehicle);

        Sanctum::actingAs($passenger);
        $joinId = $this->postJson("/api/v1/ride-offers/{$offerId}/join")->json('data.id');

        Sanctum::actingAs($provider);
        $tripId = $this->postJson("/api/v1/joins/{$joinId}/accept")->json('trip.id');
        $conversationId = $this->getJson("/api/v1/trips/{$tripId}/conversation")->json('data.id');

        $this->postJson("/api/v1/conversations/{$conversationId}/messages", ['body' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'body' => str_repeat('a', 2001),
        ])->assertUnprocessable()->assertJsonValidationErrors('body');

        $first = $this->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'body' => 'First',
        ])->json('data.id');

        $this->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'body' => 'Second',
        ])->assertCreated();

        $this->getJson("/api/v1/conversations/{$conversationId}/messages?after_id={$first}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Second');
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

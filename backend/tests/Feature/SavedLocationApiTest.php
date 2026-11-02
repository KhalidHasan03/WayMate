<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SavedLocationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_saved_locations_and_default_switching(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $first = $this->postJson('/api/v1/saved-locations', [
            'label' => 'Home',
            'address' => 'Mirpur 10',
            'lat' => 23.8103,
            'lng' => 90.4125,
            'is_default' => true,
        ])->assertCreated()->json('data.id');

        $second = $this->postJson('/api/v1/saved-locations', [
            'label' => 'Office',
            'address' => 'Motijheel',
            'lat' => 23.7330,
            'lng' => 90.4170,
            'is_default' => true,
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('saved_locations', ['id' => $first, 'is_default' => false]);
        $this->assertDatabaseHas('saved_locations', ['id' => $second, 'is_default' => true]);

        $this->getJson('/api/v1/saved-locations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $second)
            ->assertJsonPath('data.0.is_default', true);

        $this->patchJson('/api/v1/saved-locations/'.$first, ['label' => 'Flat'])
            ->assertOk()
            ->assertJsonPath('data.label', 'Flat');

        $this->deleteJson('/api/v1/saved-locations/'.$first)->assertOk();
    }
}

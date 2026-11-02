<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpsCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_be_verified_and_made_admin(): void
    {
        $user = User::factory()->create(['phone' => '+8801712345678']);

        $this->artisan('waymate:user:verify', ['phone' => '01712345678'])->assertSuccessful();
        $this->artisan('waymate:user:admin', ['phone' => '+8801712345678'])->assertSuccessful();

        $user->refresh();

        $this->assertTrue($user->hasVerifiedIdentity());
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_vehicle_can_be_approved_and_rejected(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'type' => Vehicle::TYPE_MOTORCYCLE,
            'make' => 'Bajaj',
            'model' => 'Pulsar',
            'registration_no' => 'DHAKA METRO HA 12-3456',
            'seat_capacity' => 1,
            'status' => Vehicle::STATUS_PENDING,
        ]);

        $this->artisan('waymate:vehicle:approve', ['registration' => $vehicle->registration_no])
            ->assertSuccessful();

        $this->assertSame(Vehicle::STATUS_ACTIVE, $vehicle->fresh()->status);

        $this->artisan('waymate:vehicle:approve', [
            'registration' => $vehicle->registration_no,
            '--reject' => 'Papers unreadable',
        ])->assertSuccessful();

        $vehicle->refresh();
        $this->assertSame(Vehicle::STATUS_REJECTED, $vehicle->status);
        $this->assertSame('Papers unreadable', $vehicle->rejection_reason);
    }
}

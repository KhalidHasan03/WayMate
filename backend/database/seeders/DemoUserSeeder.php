<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['phone' => '+8801700000000'],
            [
                'name' => 'WayMate Admin',
                'status' => User::STATUS_ACTIVE,
                'locale' => 'en',
                'phone_verified_at' => now(),
            ],
        );
        $admin->profile()->firstOrCreate([]);

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        $driver = User::query()->firstOrCreate(
            ['phone' => '+8801800000000'],
            [
                'name' => 'Demo Driver',
                'status' => User::STATUS_ACTIVE,
                'locale' => 'en',
                'phone_verified_at' => now(),
                'nid_verified_at' => now(),
                'license_verified_at' => now(),
            ],
        );
        $driver->profile()->firstOrCreate([]);

        if (! $driver->hasRole('user')) {
            $driver->assignRole('user');
        }

        Vehicle::query()->updateOrCreate(
            ['registration_no' => 'DHAKA-GA-1234'],
            [
                'user_id' => $driver->id,
                'type' => Vehicle::TYPE_CAR,
                'make' => 'Toyota',
                'model' => 'Corolla',
                'year' => 2018,
                'color' => 'White',
                'seat_capacity' => 4,
                'status' => Vehicle::STATUS_ACTIVE,
                'verified_at' => now(),
            ],
        );

        $passenger = User::query()->firstOrCreate(
            ['phone' => '+8801900000000'],
            [
                'name' => 'Demo Passenger',
                'status' => User::STATUS_ACTIVE,
                'locale' => 'en',
                'phone_verified_at' => now(),
            ],
        );
        $passenger->profile()->firstOrCreate([]);

        if (! $passenger->hasRole('user')) {
            $passenger->assignRole('user');
        }
    }
}

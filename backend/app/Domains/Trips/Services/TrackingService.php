<?php

namespace App\Domains\Trips\Services;

use App\Models\Trip;
use App\Models\TripLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrackingService
{
    public function start(Trip $trip, User $actor): Trip
    {
        return DB::transaction(function () use ($trip, $actor) {
            $trip = Trip::query()->whereKey($trip->id)->lockForUpdate()->firstOrFail();

            if ($trip->coordinator_id !== $actor->id && $trip->provider_id !== $actor->id) {
                $this->fail('Only the driver or coordinator can start tracking.');
            }

            if (! $trip->isActive()) {
                $this->fail('Tracking is only available for active trips.');
            }

            if ($trip->tracking_enabled) {
                return $trip;
            }

            $trip->update([
                'tracking_enabled' => true,
                'tracking_started_at' => now(),
                'tracking_stopped_at' => null,
            ]);

            return $trip->fresh();
        });
    }

    public function stop(Trip $trip, User $actor): Trip
    {
        return DB::transaction(function () use ($trip, $actor) {
            $trip = Trip::query()->whereKey($trip->id)->lockForUpdate()->firstOrFail();

            if ($trip->coordinator_id !== $actor->id && $trip->provider_id !== $actor->id) {
                $this->fail('Only the driver or coordinator can stop tracking.');
            }

            if ($trip->tracking_enabled) {
                $trip->update([
                    'tracking_enabled' => false,
                    'tracking_stopped_at' => now(),
                ]);
            }

            return $trip->fresh();
        });
    }

    /**
     * @param  array{lat: float, lng: float, accuracy_m?: float|null, speed_mps?: float|null, heading_deg?: float|null, recorded_at?: string|null}  $data
     */
    public function record(Trip $trip, User $actor, array $data): TripLocation
    {
        if ($trip->coordinator_id !== $actor->id && $trip->provider_id !== $actor->id) {
            $this->fail('Only the driver or coordinator can share a location.');
        }

        if (! $trip->tracking_enabled || ! $trip->isActive()) {
            $this->fail('Tracking is not active for this trip.');
        }

        $location = $trip->locations()->create([
            'user_id' => $actor->id,
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'accuracy_m' => $data['accuracy_m'] ?? null,
            'speed_mps' => $data['speed_mps'] ?? null,
            'heading_deg' => $data['heading_deg'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);

        $actor->forceFill([
            'last_lat' => $data['lat'],
            'last_lng' => $data['lng'],
            'last_accuracy_m' => $data['accuracy_m'] ?? null,
            'last_location_at' => $location->recorded_at,
        ])->save();

        return $location;
    }

    /**
     * @return array{trip: Trip, locations: \Illuminate\Support\Collection<int, TripLocation>}
     */
    public function snapshot(Trip $trip, ?int $afterId = null): array
    {
        $locations = $trip->locations()
            ->when($afterId !== null, fn ($query) => $query->where('id', '>', $afterId))
            ->latest('recorded_at')
            ->limit(200)
            ->get()
            ->sortBy('recorded_at')
            ->values();

        return ['trip' => $trip, 'locations' => $locations];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['tracking' => $message]);
    }
}

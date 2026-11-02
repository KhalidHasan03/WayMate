<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Trips\Services\TrackingService;
use App\Http\Controllers\Controller;
use App\Http\Resources\TripLocationResource;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TripTrackingController extends Controller
{
    public function __construct(private readonly TrackingService $tracking) {}

    public function show(Request $request, Trip $trip): JsonResponse
    {
        Gate::authorize('view', $trip);

        $snapshot = $this->tracking->snapshot(
            $trip,
            $request->integer('after_id') ?: null,
        );

        return $this->payload($snapshot);
    }

    public function start(Request $request, Trip $trip): JsonResponse
    {
        Gate::authorize('manageLifecycle', $trip);

        $trip = $this->tracking->start($trip, $request->user());

        return $this->payload(['trip' => $trip, 'locations' => collect()]);
    }

    public function point(Request $request, Trip $trip): JsonResponse
    {
        Gate::authorize('manageLifecycle', $trip);

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['sometimes', 'nullable', 'numeric', 'between:0,10000'],
            'speed_mps' => ['sometimes', 'nullable', 'numeric', 'between:0,150'],
            'heading_deg' => ['sometimes', 'nullable', 'numeric', 'between:0,360'],
            'recorded_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $location = $this->tracking->record($trip, $request->user(), $data);

        return response()->json(['data' => new TripLocationResource($location)], 201);
    }

    public function stop(Request $request, Trip $trip): JsonResponse
    {
        Gate::authorize('manageLifecycle', $trip);

        $trip = $this->tracking->stop($trip, $request->user());

        return $this->payload(['trip' => $trip, 'locations' => collect()]);
    }

    private function payload(array $snapshot): JsonResponse
    {
        /** @var Trip $trip */
        $trip = $snapshot['trip'];

        return response()->json([
            'data' => [
                'trip_id' => $trip->id,
                'tracking_enabled' => (bool) $trip->tracking_enabled,
                'tracking_started_at' => $trip->tracking_started_at?->toIso8601String(),
                'tracking_stopped_at' => $trip->tracking_stopped_at?->toIso8601String(),
                'locations' => TripLocationResource::collection($snapshot['locations']),
            ],
        ]);
    }
}

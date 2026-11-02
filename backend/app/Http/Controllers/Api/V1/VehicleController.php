<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\VehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\RideOffer;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class VehicleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $vehicles = $request->user()
            ->vehicles()
            ->with('documents')
            ->latest()
            ->get();

        return VehicleResource::collection($vehicles);
    }

    public function store(VehicleRequest $request): JsonResponse
    {
        $vehicle = $request->user()->vehicles()->create([
            ...$request->validated(),
            'status' => Vehicle::STATUS_PENDING,
        ]);

        return (new VehicleResource($vehicle->load('documents')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Vehicle $vehicle): VehicleResource
    {
        Gate::authorize('view', $vehicle);

        return new VehicleResource($vehicle->load('documents'));
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): VehicleResource
    {
        Gate::authorize('update', $vehicle);

        $vehicle->fill($request->validated());

        $criticalChanged = $vehicle->isDirty(['type', 'registration_no', 'year']);

        if ($criticalChanged && $vehicle->status === Vehicle::STATUS_ACTIVE) {
            $vehicle->status = Vehicle::STATUS_PENDING;
            $vehicle->verified_at = null;
        }

        $vehicle->save();

        return new VehicleResource($vehicle->load('documents'));
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        Gate::authorize('delete', $vehicle);

        $hasActiveOffers = $vehicle->offers()
            ->whereIn('status', [RideOffer::STATUS_OPEN, RideOffer::STATUS_IN_PROGRESS])
            ->exists();

        $hasActiveTrips = Trip::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereIn('status', [Trip::STATUS_FORMING, Trip::STATUS_READY, Trip::STATUS_IN_PROGRESS])
            ->exists();

        if ($hasActiveOffers || $hasActiveTrips) {
            return response()->json([
                'message' => 'Cancel active offers and finish active trips before deleting this vehicle.',
            ], 422);
        }

        $vehicle->delete();

        return response()->json(['message' => 'Vehicle deleted.']);
    }
}

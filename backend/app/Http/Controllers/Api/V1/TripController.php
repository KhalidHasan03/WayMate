<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Trips\Services\TripService;
use App\Http\Controllers\Controller;
use App\Http\Resources\TripEventResource;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TripController extends Controller
{
    public function __construct(private readonly TripService $trips) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $trips = Trip::query()
            ->with([
                'coordinator',
                'provider',
                'vehicle',
                'participants.user',
                'rideRequest',
                'rideOffer.provider',
                'rideOffer.vehicle',
            ])
            ->where(fn ($query) => $query
                ->where('coordinator_id', $user->id)
                ->orWhere('provider_id', $user->id)
                ->orWhereHas('participants', fn ($participants) => $participants->where('user_id', $user->id)))
            ->latest()
            ->paginate(20);

        return TripResource::collection($trips);
    }

    public function show(Trip $trip): TripResource
    {
        Gate::authorize('view', $trip);

        return new TripResource($trip->load([
            'coordinator',
            'provider',
            'vehicle',
            'participants.user',
            'rideRequest',
            'rideOffer.provider',
            'rideOffer.vehicle',
        ]));
    }

    public function events(Trip $trip): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('view', $trip);

        $events = $trip->events()->with('actor')->oldest()->get();

        return response()->json(['data' => TripEventResource::collection($events)]);
    }

    public function start(Request $request, Trip $trip): TripResource
    {
        Gate::authorize('manageLifecycle', $trip);

        return new TripResource($this->trips->start($trip, $request->user()));
    }

    public function complete(Request $request, Trip $trip): TripResource
    {
        Gate::authorize('manageLifecycle', $trip);

        return new TripResource($this->trips->complete($trip, $request->user()));
    }

    public function cancel(Request $request, Trip $trip): TripResource
    {
        Gate::authorize('manageLifecycle', $trip);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        return new TripResource($this->trips->cancel($trip, $request->user(), $data['reason'] ?? null));
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Geo\Services\RouteService;
use App\Domains\Geo\Support\Geohash;
use App\Http\Controllers\Controller;
use App\Http\Requests\RideRequestRequest;
use App\Http\Resources\RideRequestResource;
use App\Models\JoinRequest;
use App\Models\RideRequest;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RideRequestController extends Controller
{
    public function __construct(
        private readonly RouteService $routes,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()
            ->rideRequests()
            ->with(['activeTrip', 'creator'])
            ->withCount('joinRequests')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return RideRequestResource::collection($query->paginate(20));
    }

    public function store(RideRequestRequest $request): JsonResponse
    {
        $data = $request->validated();
        $accepting = $data['accepting_copassengers'] ?? true;
        $poolMax = $accepting
            ? ($data['pool_max_seats'] ?? $data['seats_needed'])
            : $data['seats_needed'];

        $route = $this->routes->route(
            (float) $data['origin_lat'],
            (float) $data['origin_lng'],
            (float) $data['dest_lat'],
            (float) $data['dest_lng'],
        );

        $rideRequest = RideRequest::create([
            ...$data,
            'creator_id' => $request->user()->id,
            'origin_geohash' => Geohash::encode((float) $data['origin_lat'], (float) $data['origin_lng']),
            'dest_geohash' => Geohash::encode((float) $data['dest_lat'], (float) $data['dest_lng']),
            'route_path' => $route->path,
            'route_polyline' => $route->encodedPolyline,
            'distance_m' => $route->distanceM,
            'duration_s' => $route->durationS,
            'depart_window_min' => (int) config('waymate.matching.request_window_min', 90),
            'pool_max_seats' => $poolMax,
            'accepting_copassengers' => $accepting,
            'status' => RideRequest::STATUS_OPEN,
            'expires_at' => Carbon::parse($data['depart_at'])
                ->setTimezone(config('app.timezone'))
                ->addMinutes((int) config('waymate.matching.request_window_min', 90)),
        ]);

        return (new RideRequestResource($rideRequest->load(['activeTrip', 'creator'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(RideRequest $rideRequest): RideRequestResource
    {
        Gate::authorize('view', $rideRequest);

        return new RideRequestResource(
            $rideRequest->load([
                'activeTrip',
                'creator',
                'joinRequests' => fn ($query) => $query->latest(),
                'joinRequests.user',
            ]),
        );
    }

    public function update(RideRequestRequest $request, RideRequest $rideRequest): RideRequestResource|JsonResponse
    {
        Gate::authorize('update', $rideRequest);

        if ($rideRequest->status !== RideRequest::STATUS_OPEN) {
            return response()->json(['message' => 'Only open requests can be updated.'], 422);
        }

        $hasActiveTrip = $rideRequest->trips()
            ->whereIn('status', [Trip::STATUS_FORMING, Trip::STATUS_READY, Trip::STATUS_IN_PROGRESS])
            ->exists();

        $hasAcceptedJoins = $rideRequest->joinRequests()
            ->where('status', JoinRequest::STATUS_ACCEPTED)
            ->exists();

        if ($hasActiveTrip || $hasAcceptedJoins) {
            return response()->json([
                'message' => 'This request already has an active trip or accepted riders and cannot be edited.',
            ], 422);
        }

        $data = $request->validated();
        $rideRequest->fill($data);

        if (isset($data['seats_needed']) && $rideRequest->pool_max_seats < $rideRequest->seats_needed) {
            return response()->json([
                'message' => 'Pool capacity must be at least the number of seats needed.',
            ], 422);
        }

        if ($rideRequest->isDirty('depart_at')) {
            $rideRequest->expires_at = $rideRequest->depart_at
                ->copy()
                ->addMinutes((int) config('waymate.matching.request_window_min', 90));
        }

        $coordinateFields = ['origin_lat', 'origin_lng', 'dest_lat', 'dest_lng'];

        if ($rideRequest->isDirty($coordinateFields)) {
            $route = $this->routes->route(
                (float) $rideRequest->origin_lat,
                (float) $rideRequest->origin_lng,
                (float) $rideRequest->dest_lat,
                (float) $rideRequest->dest_lng,
            );

            $rideRequest->forceFill([
                'route_path' => $route->path,
                'route_polyline' => $route->encodedPolyline,
                'distance_m' => $route->distanceM,
                'duration_s' => $route->durationS,
                'origin_geohash' => Geohash::encode((float) $rideRequest->origin_lat, (float) $rideRequest->origin_lng),
                'dest_geohash' => Geohash::encode((float) $rideRequest->dest_lat, (float) $rideRequest->dest_lng),
            ]);
        }

        $rideRequest->save();

        return new RideRequestResource($rideRequest->load(['activeTrip', 'creator']));
    }

    public function cancel(RideRequest $rideRequest): JsonResponse
    {
        Gate::authorize('cancel', $rideRequest);

        if ($rideRequest->activeTrip()->exists()) {
            return response()->json([
                'message' => 'Cancel the active trip instead of the request.',
            ], 422);
        }

        DB::transaction(function () use ($rideRequest) {
            $rideRequest->update(['status' => RideRequest::STATUS_CANCELLED]);

            $rideRequest->joinRequests()
                ->where('status', JoinRequest::STATUS_PENDING)
                ->update([
                    'status' => JoinRequest::STATUS_CANCELLED,
                    'decided_at' => now(),
                ]);
        });

        return response()->json(['message' => 'Ride request cancelled.']);
    }
}

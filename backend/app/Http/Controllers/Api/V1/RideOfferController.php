<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Geo\Services\RouteService;
use App\Domains\Geo\Support\Geohash;
use App\Http\Controllers\Controller;
use App\Http\Requests\RideOfferRequest;
use App\Http\Resources\RideOfferResource;
use App\Models\JoinRequest;
use App\Models\RideOffer;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RideOfferController extends Controller
{
    public function __construct(
        private readonly RouteService $routes,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()
            ->rideOffers()
            ->with(['provider', 'vehicle'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return RideOfferResource::collection($query->paginate(20));
    }

    public function store(RideOfferRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->canProvideRides()) {
            return response()->json([
                'message' => 'Verify your identity and add an active vehicle before offering a ride.',
            ], 403);
        }

        $data = $request->validated();

        $route = $this->routes->route(
            (float) $data['origin_lat'],
            (float) $data['origin_lng'],
            (float) $data['dest_lat'],
            (float) $data['dest_lng'],
        );

        $offer = RideOffer::create([
            ...$data,
            'provider_id' => $user->id,
            'origin_geohash' => Geohash::encode((float) $data['origin_lat'], (float) $data['origin_lng']),
            'dest_geohash' => Geohash::encode((float) $data['dest_lat'], (float) $data['dest_lng']),
            'route_path' => $route->path,
            'route_polyline' => $route->encodedPolyline,
            'distance_m' => $route->distanceM,
            'duration_s' => $route->durationS,
            'depart_window_min' => (int) config('waymate.matching.offer_window_min', 60),
            'seats_allocated' => 0,
            'provider_participates' => $data['provider_participates'] ?? true,
            'status' => RideOffer::STATUS_OPEN,
            'expires_at' => Carbon::parse($data['depart_at'])
                ->setTimezone(config('app.timezone'))
                ->addMinutes((int) config('waymate.matching.offer_window_min', 60)),
        ]);

        return (new RideOfferResource($offer->load(['provider', 'vehicle'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(RideOffer $rideOffer): RideOfferResource
    {
        Gate::authorize('view', $rideOffer);

        return new RideOfferResource(
            $rideOffer->load([
                'provider',
                'vehicle.documents',
                'joinRequests' => fn ($query) => $query->latest(),
                'joinRequests.user',
            ]),
        );
    }

    public function update(RideOfferRequest $request, RideOffer $rideOffer): RideOfferResource|JsonResponse
    {
        Gate::authorize('update', $rideOffer);

        if ($rideOffer->status !== RideOffer::STATUS_OPEN) {
            return response()->json(['message' => 'Only open offers can be updated.'], 422);
        }

        $hasActiveTrip = $rideOffer->trips()
            ->whereIn('status', [Trip::STATUS_FORMING, Trip::STATUS_READY, Trip::STATUS_IN_PROGRESS])
            ->exists();

        if ($hasActiveTrip || $rideOffer->seats_allocated > 0) {
            return response()->json([
                'message' => 'This offer already has riders or an active trip and cannot be edited.',
            ], 422);
        }

        $data = $request->validated();
        $rideOffer->fill($data);

        if (isset($data['seats_total']) && $rideOffer->seats_total < $rideOffer->seats_allocated) {
            return response()->json([
                'message' => 'Seats cannot be reduced below the number already allocated.',
            ], 422);
        }

        if ($rideOffer->isDirty('depart_at')) {
            $rideOffer->expires_at = $rideOffer->depart_at
                ->copy()
                ->addMinutes((int) config('waymate.matching.offer_window_min', 60));
        }

        $coordinateFields = ['origin_lat', 'origin_lng', 'dest_lat', 'dest_lng'];

        if ($rideOffer->isDirty($coordinateFields)) {
            $route = $this->routes->route(
                (float) $rideOffer->origin_lat,
                (float) $rideOffer->origin_lng,
                (float) $rideOffer->dest_lat,
                (float) $rideOffer->dest_lng,
            );

            $rideOffer->forceFill([
                'route_path' => $route->path,
                'route_polyline' => $route->encodedPolyline,
                'distance_m' => $route->distanceM,
                'duration_s' => $route->durationS,
                'origin_geohash' => Geohash::encode((float) $rideOffer->origin_lat, (float) $rideOffer->origin_lng),
                'dest_geohash' => Geohash::encode((float) $rideOffer->dest_lat, (float) $rideOffer->dest_lng),
            ]);
        }

        $rideOffer->save();

        return new RideOfferResource($rideOffer->load(['provider', 'vehicle']));
    }

    public function cancel(RideOffer $rideOffer): JsonResponse
    {
        Gate::authorize('cancel', $rideOffer);

        if ($rideOffer->activeTrip()->exists()) {
            return response()->json([
                'message' => 'Cancel the active trip instead of the offer.',
            ], 422);
        }

        DB::transaction(function () use ($rideOffer) {
            $rideOffer->update(['status' => RideOffer::STATUS_CANCELLED]);

            $rideOffer->joinRequests()
                ->where('status', JoinRequest::STATUS_PENDING)
                ->update([
                    'status' => JoinRequest::STATUS_CANCELLED,
                    'decided_at' => now(),
                ]);
        });

        return response()->json(['message' => 'Ride offer cancelled.']);
    }
}

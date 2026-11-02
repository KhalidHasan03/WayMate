<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Trips\Services\JoinService;
use App\Http\Controllers\Controller;
use App\Http\Requests\JoinOfferRequest;
use App\Http\Requests\ProviderJoinRequest;
use App\Http\Resources\JoinRequestResource;
use App\Http\Resources\TripResource;
use App\Models\JoinRequest;
use App\Models\RideOffer;
use App\Models\RideRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class JoinRequestController extends Controller
{
    public function incoming(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $joins = JoinRequest::query()
            ->with(['user', 'vehicle', 'rideRequest', 'rideOffer'])
            ->where(fn ($query) => $query
                ->whereHas('rideOffer', fn ($offer) => $offer->where('provider_id', $user->id))
                ->orWhereHas('rideRequest', fn ($ride) => $ride->where('creator_id', $user->id)))
            ->latest()
            ->paginate(20);

        return JoinRequestResource::collection($joins);
    }

    public function outgoing(Request $request): AnonymousResourceCollection
    {
        $joins = JoinRequest::query()
            ->with(['user', 'vehicle', 'rideRequest', 'rideOffer'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return JoinRequestResource::collection($joins);
    }

    public function storeForOffer(
        JoinOfferRequest $request,
        RideOffer $rideOffer,
        JoinService $joins,
    ): JsonResponse {
        $join = $joins->joinOffer($request->user(), $rideOffer, $request->validated());

        return (new JoinRequestResource($join->load(['user', 'rideOffer'])))
            ->response()
            ->setStatusCode(201);
    }

    public function storeForRequest(
        ProviderJoinRequest $request,
        RideRequest $rideRequest,
        JoinService $joins,
    ): JsonResponse {
        $join = $joins->joinRequest($request->user(), $rideRequest, $request->validated());

        return (new JoinRequestResource($join->load(['user', 'vehicle', 'rideRequest'])))
            ->response()
            ->setStatusCode(201);
    }

    public function accept(JoinRequest $join, JoinService $joins): JsonResponse
    {
        Gate::authorize('accept', $join);

        $trip = $joins->accept($join, request()->user());

        return response()->json([
            'message' => 'Join request accepted.',
            'data' => new JoinRequestResource($join->fresh()->load(['user', 'rideRequest', 'rideOffer'])),
            'trip' => new TripResource($trip->load([
                'coordinator',
                'provider',
                'vehicle',
                'participants.user',
                'rideRequest',
                'rideOffer.provider',
                'rideOffer.vehicle',
            ])),
        ]);
    }

    public function decline(JoinRequest $join, JoinService $joins): JsonResponse
    {
        Gate::authorize('decline', $join);

        $joins->decline($join, request()->user());

        return response()->json(['message' => 'Join request declined.']);
    }

    public function withdraw(JoinRequest $join, JoinService $joins): JsonResponse
    {
        Gate::authorize('withdraw', $join);

        $joins->withdraw($join, request()->user());

        return response()->json(['message' => 'Join request withdrawn.']);
    }
}

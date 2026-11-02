<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Matching\Services\MatchService;
use App\Http\Controllers\Controller;
use App\Http\Resources\RideOfferResource;
use App\Http\Resources\RideRequestResource;
use App\Models\RideOffer;
use App\Models\RideRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MatchController extends Controller
{
    public function forRequest(
        Request $request,
        RideRequest $rideRequest,
        MatchService $matcher,
    ): JsonResponse {
        Gate::authorize('view', $rideRequest);

        $limit = (int) $request->integer('limit', 20);
        $rows = $matcher->forRequest($rideRequest, $limit);

        return response()->json([
            'data' => array_map(fn (array $row) => [
                'offer' => new RideOfferResource($row['offer']),
                'match' => $row['match']->toArray(),
                'estimated_share_poisha' => $row['estimated_share_poisha'],
                'estimated_total_poisha' => $row['estimated_total_poisha'],
            ], $rows),
        ]);
    }

    public function forOffer(
        Request $request,
        RideOffer $rideOffer,
        MatchService $matcher,
    ): JsonResponse {
        Gate::authorize('view', $rideOffer);

        $limit = (int) $request->integer('limit', 20);
        $rows = $matcher->forOffer($rideOffer, $limit);

        return response()->json([
            'data' => array_map(fn (array $row) => [
                'request' => new RideRequestResource($row['request']),
                'match' => $row['match']->toArray(),
                'estimated_share_poisha' => $row['estimated_share_poisha'],
                'estimated_total_poisha' => $row['estimated_total_poisha'],
            ], $rows),
        ]);
    }
}

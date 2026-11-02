<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Matching\Services\MatchService;
use App\Http\Controllers\Controller;
use App\Http\Requests\RideSearchRequest;
use App\Http\Resources\RideOfferResource;
use App\Models\RideRequest;
use Illuminate\Http\JsonResponse;

class RideSearchController extends Controller
{
    public function index(RideSearchRequest $request, MatchService $matcher): JsonResponse
    {
        $data = $request->validated();

        $rows = $matcher->searchOffers(
            $request->user(),
            (float) $data['origin_lat'],
            (float) $data['origin_lng'],
            (float) $data['dest_lat'],
            (float) $data['dest_lng'],
            $request->date('depart_at'),
            (int) ($data['seats_needed'] ?? 1),
            $request->boolean('women_only'),
            $data['transport_preference'] ?? RideRequest::PREF_ANY,
            (int) ($data['limit'] ?? 20),
        );

        return response()->json([
            'data' => array_map(fn (array $row) => [
                'offer' => new RideOfferResource($row['offer']),
                'match' => $row['match']->toArray(),
                'estimated_share_poisha' => $row['estimated_share_poisha'],
                'estimated_total_poisha' => $row['estimated_total_poisha'],
            ], $rows),
            'meta' => ['count' => count($rows)],
        ]);
    }
}

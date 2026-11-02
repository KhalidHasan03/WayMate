<?php

namespace App\Domains\Matching\Services;

use App\Domains\Matching\Data\MatchResult;
use App\Models\RideOffer;
use App\Models\RideRequest;
use App\Models\User;
use Carbon\CarbonInterface;

class MatchService
{
    public function __construct(
        private readonly RouteMatcher $matcher,
    ) {}

    /**
     * Offers that can serve the given request, best first.
     *
     * @return array<int, array{offer: RideOffer, match: MatchResult, estimated_share_poisha: int, estimated_total_poisha: int}>
     */
    public function forRequest(RideRequest $request, int $limit = 20): array
    {
        $request->loadMissing('creator');

        $windowMin = (int) config('waymate.matching.request_window_min', 90);
        $searchLimit = (int) config('waymate.matching.search_limit', 100);

        $departAt = $request->depart_at;

        $offers = RideOffer::query()
            ->with(['provider', 'vehicle'])
            ->where('status', RideOffer::STATUS_OPEN)
            ->whereColumn('seats_allocated', '<', 'seats_total')
            ->where('provider_id', '!=', $request->creator_id)
            ->whereBetween('depart_at', [
                $departAt->copy()->subMinutes($windowMin),
                $departAt->copy()->addMinutes($windowMin),
            ])
            ->where('depart_at', '>=', now()->addMinutes((int) config('waymate.matching.lead_time_min', 15)))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($request->women_only, fn ($query) => $query->where('women_only', true))
            ->when($request->transport_preference === RideRequest::PREF_PRIVATE, fn ($query) => $query
                ->whereHas('vehicle', fn ($vehicle) => $vehicle->where('type', 'car')))
            ->when($request->transport_preference === RideRequest::PREF_EXTERNAL, fn ($query) => $query
                ->whereHas('vehicle', fn ($vehicle) => $vehicle->whereIn('type', ['motorcycle', 'scooter', 'other'])))
            ->limit($searchLimit)
            ->get();

        $rows = [];

        foreach ($offers as $offer) {
            $seatsAvailable = $offer->seatsAvailable();

            if ($seatsAvailable < $request->seats_needed) {
                continue;
            }

            if ($offer->women_only && $request->creator?->gender !== 'female') {
                continue;
            }

            if ($offer->women_only && $offer->provider?->gender !== 'female') {
                continue;
            }

            if ($request->women_only && $offer->provider?->gender !== 'female') {
                continue;
            }

            if (! $this->transportAllows($request, $offer)) {
                continue;
            }

            $timeDeltaMin = abs($departAt->diffInMinutes($offer->depart_at));

            $match = $this->matcher->match(
                $this->routePath($offer),
                (float) $request->origin_lat,
                (float) $request->origin_lng,
                (float) $request->dest_lat,
                (float) $request->dest_lng,
                $timeDeltaMin,
                $windowMin,
                $seatsAvailable / max(1, (int) $offer->seats_total),
            );

            if (! $match) {
                continue;
            }

            $share = $this->estimatedShare($offer);
            $rows[] = [
                'offer' => $offer,
                'match' => $match,
                'estimated_share_poisha' => $share,
                'estimated_total_poisha' => $share * (int) $request->seats_needed,
            ];
        }

        return $this->rank($rows, $limit);
    }

    /**
     * Search open offers for a passenger without creating a ride request.
     *
     * @return array<int, array{offer: RideOffer, match: MatchResult, estimated_share_poisha: int, estimated_total_poisha: int}>
     */
    public function searchOffers(
        ?User $user,
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        CarbonInterface $departAt,
        int $seatsNeeded = 1,
        bool $womenOnly = false,
        string $transportPreference = RideRequest::PREF_ANY,
        int $limit = 20,
    ): array {
        $windowMin = (int) config('waymate.matching.request_window_min', 90);
        $searchLimit = (int) config('waymate.matching.search_limit', 100);

        $offers = RideOffer::query()
            ->with(['provider', 'vehicle'])
            ->where('status', RideOffer::STATUS_OPEN)
            ->whereColumn('seats_allocated', '<', 'seats_total')
            ->when($user, fn ($query) => $query->where('provider_id', '!=', $user->id))
            ->whereBetween('depart_at', [
                $departAt->copy()->subMinutes($windowMin),
                $departAt->copy()->addMinutes($windowMin),
            ])
            ->where('depart_at', '>=', now()->addMinutes((int) config('waymate.matching.lead_time_min', 15)))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($womenOnly, fn ($query) => $query->where('women_only', true))
            ->when($transportPreference === RideRequest::PREF_PRIVATE, fn ($query) => $query
                ->whereHas('vehicle', fn ($vehicle) => $vehicle->where('type', 'car')))
            ->when($transportPreference === RideRequest::PREF_EXTERNAL, fn ($query) => $query
                ->whereHas('vehicle', fn ($vehicle) => $vehicle->whereIn('type', ['motorcycle', 'scooter', 'other'])))
            ->limit($searchLimit)
            ->get();

        $rows = [];

        foreach ($offers as $offer) {
            $seatsAvailable = $offer->seatsAvailable();

            if ($seatsAvailable < $seatsNeeded) {
                continue;
            }

            if ($womenOnly && $offer->provider?->gender !== 'female') {
                continue;
            }

            if ($user !== null && $offer->provider_id === $user->id) {
                continue;
            }

            $timeDeltaMin = abs($departAt->diffInMinutes($offer->depart_at));

            $match = $this->matcher->match(
                $this->routePath($offer),
                $originLat,
                $originLng,
                $destLat,
                $destLng,
                $timeDeltaMin,
                $windowMin,
                $seatsAvailable / max(1, (int) $offer->seats_total),
            );

            if (! $match) {
                continue;
            }

            $share = $this->estimatedShare($offer);
            $rows[] = [
                'offer' => $offer,
                'match' => $match,
                'estimated_share_poisha' => $share,
                'estimated_total_poisha' => $share * $seatsNeeded,
            ];
        }

        return $this->rank($rows, $limit);
    }

    /**
     * Requests that fit along the given offer, best first.
     *
     * @return array<int, array{request: RideRequest, match: MatchResult, estimated_share_poisha: int, estimated_total_poisha: int}>
     */
    public function forOffer(RideOffer $offer, int $limit = 20): array
    {
        $offer->loadMissing('provider');

        $windowMin = (int) config('waymate.matching.request_window_min', 90);
        $searchLimit = (int) config('waymate.matching.search_limit', 100);

        $departAt = $offer->depart_at;
        $seatsAvailable = $offer->seatsAvailable();

        if ($seatsAvailable < 1) {
            return [];
        }

        $requests = RideRequest::query()
            ->with('creator')
            ->where('status', RideRequest::STATUS_OPEN)
            ->where('creator_id', '!=', $offer->provider_id)
            ->whereBetween('depart_at', [
                $departAt->copy()->subMinutes($windowMin),
                $departAt->copy()->addMinutes($windowMin),
            ])
            ->where('depart_at', '>=', now()->addMinutes((int) config('waymate.matching.lead_time_min', 15)))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where('seats_needed', '<=', $seatsAvailable)
            ->when($offer->women_only, fn ($query) => $query
                ->whereHas('creator', fn ($creator) => $creator->where('gender', 'female')))
            ->limit($searchLimit)
            ->get();

        $share = $this->estimatedShare($offer);
        $rows = [];

        foreach ($requests as $request) {
            if ($request->women_only && $offer->provider?->gender !== 'female') {
                continue;
            }

            if (! $this->transportAllows($request, $offer)) {
                continue;
            }

            $timeDeltaMin = abs($departAt->diffInMinutes($request->depart_at));

            $match = $this->matcher->match(
                $this->routePath($offer),
                (float) $request->origin_lat,
                (float) $request->origin_lng,
                (float) $request->dest_lat,
                (float) $request->dest_lng,
                $timeDeltaMin,
                $windowMin,
                $seatsAvailable / max(1, (int) $offer->seats_total),
            );

            if (! $match) {
                continue;
            }

            $rows[] = [
                'request' => $request,
                'match' => $match,
                'estimated_share_poisha' => $share,
                'estimated_total_poisha' => $share * (int) $request->seats_needed,
            ];
        }

        return $this->rank($rows, $limit);
    }

    private function transportAllows(RideRequest $request, RideOffer $offer): bool
    {
        $vehicleType = $offer->vehicle?->type;

        return match ($request->transport_preference) {
            RideRequest::PREF_PRIVATE => $vehicleType === 'car',
            RideRequest::PREF_EXTERNAL => in_array($vehicleType, ['motorcycle', 'scooter', 'other'], true),
            default => true,
        };
    }

    private function estimatedShare(RideOffer $offer): int
    {
        $participants = (int) $offer->seats_total + ($offer->provider_participates ? 1 : 0);

        return (int) floor((int) $offer->cost_total_poisha / max(1, $participants));
    }

    /**
     * @return array<int, array{0: float, 1: float}>
     */
    private function routePath(RideOffer $offer): array
    {
        $path = $offer->route_path;

        if (is_array($path) && count($path) >= 2) {
            return $path;
        }

        return [
            [(float) $offer->origin_lat, (float) $offer->origin_lng],
            [(float) $offer->dest_lat, (float) $offer->dest_lng],
        ];
    }

    /**
     * @param  array<int, array{match: MatchResult}>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function rank(array $rows, int $limit): array
    {
        usort($rows, function (array $a, array $b) {
            if ($a['match']->score !== $b['match']->score) {
                return $b['match']->score <=> $a['match']->score;
            }

            $aDepart = $a['offer']->depart_at ?? $a['request']->depart_at;
            $bDepart = $b['offer']->depart_at ?? $b['request']->depart_at;

            return $aDepart <=> $bDepart;
        });

        return array_slice($rows, 0, max(1, min(50, $limit)));
    }
}

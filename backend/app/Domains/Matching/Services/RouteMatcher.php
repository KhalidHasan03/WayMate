<?php

namespace App\Domains\Matching\Services;

use App\Domains\Geo\Support\GeoMath;
use App\Domains\Matching\Data\MatchResult;

class RouteMatcher
{
    /**
     * Score a pickup/drop-off pair against a candidate route.
     *
     * @param  array<int, array{0: float|int, 1: float|int}>  $path
     */
    public function match(
        array $path,
        float $pickupLat,
        float $pickupLng,
        float $dropLat,
        float $dropLng,
        float $timeDeltaMin,
        int $windowMin,
        float $seatFactor = 1.0,
    ): ?MatchResult {
        $path = $this->normalizePath($path);

        if (count($path) < 2) {
            return null;
        }

        $corridorM = (float) config('waymate.matching.corridor_m', 1200);
        $minOverlapM = (float) config('waymate.matching.min_overlap_m', 500);
        $minCos = (float) config('waymate.matching.min_cos_theta', 0.5);

        $pickup = $this->nearestPointOnPath($path, $pickupLat, $pickupLng);
        $dropoff = $this->nearestPointOnPath($path, $dropLat, $dropLng);

        if ($pickup['distance'] > $corridorM || $dropoff['distance'] > $corridorM) {
            return null;
        }

        if ($pickup['along'] >= $dropoff['along']) {
            return null;
        }

        $overlapM = $dropoff['along'] - $pickup['along'];

        if ($overlapM < $minOverlapM) {
            return null;
        }

        $routeBearing = GeoMath::bearingDegrees(
            (float) $path[0][0],
            (float) $path[0][1],
            (float) $path[count($path) - 1][0],
            (float) $path[count($path) - 1][1],
        );
        $tripBearing = GeoMath::bearingDegrees($pickupLat, $pickupLng, $dropLat, $dropLng);
        $cosTheta = GeoMath::cosTheta($routeBearing, $tripBearing);

        if ($cosTheta < $minCos) {
            return null;
        }

        $directM = max(1.0, GeoMath::haversineMeters($pickupLat, $pickupLng, $dropLat, $dropLng));
        $deviationM = $pickup['distance'] + $dropoff['distance'];

        $corridorScore = max(0.0, 1 - $deviationM / (2 * $corridorM));
        $directionScore = max(0.0, $cosTheta);
        $overlapScore = min(1.0, $overlapM / $directM);
        $timeScore = max(0.0, 1 - abs($timeDeltaMin) / max(1, $windowMin));
        $seatScore = max(0.0, min(1.0, $seatFactor));

        $score = (int) round(100 * (
            0.40 * $corridorScore
            + 0.30 * $directionScore
            + 0.20 * $overlapScore
            + 0.05 * $timeScore
            + 0.05 * $seatScore
        ));

        if ($score < (int) config('waymate.matching.score_threshold', 55)) {
            return null;
        }

        return new MatchResult(
            pickupDeviationM: $pickup['distance'],
            dropoffDeviationM: $dropoff['distance'],
            overlapM: $overlapM,
            detourM: $deviationM,
            cosTheta: $cosTheta,
            pickupProgressM: $pickup['along'],
            dropoffProgressM: $dropoff['along'],
            score: $score,
        );
    }

    /**
     * @param  array<int, array{0: float|int, 1: float|int}>  $path
     * @return array<int, array{0: float, 1: float}>
     */
    private function normalizePath(array $path): array
    {
        $normalized = [];

        foreach ($path as $point) {
            if (! is_array($point) || count($point) < 2) {
                continue;
            }

            $lat = (float) $point[0];
            $lng = (float) $point[1];

            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                continue;
            }

            $normalized[] = [$lat, $lng];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $path
     * @return array{distance: float, along: float}
     */
    private function nearestPointOnPath(array $path, float $lat, float $lng): array
    {
        $bestDistance = INF;
        $bestAlong = 0.0;
        $cumulative = 0.0;

        for ($i = 1; $i < count($path); $i++) {
            [$aLat, $aLng] = $path[$i - 1];
            [$bLat, $bLng] = $path[$i];

            $segmentLength = GeoMath::haversineMeters($aLat, $aLng, $bLat, $bLng);

            $distance = GeoMath::pointToSegmentMeters($lat, $lng, $aLat, $aLng, $bLat, $bLng);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;

                $t = $this->projectionFactor($lat, $lng, $aLat, $aLng, $bLat, $bLng);
                $bestAlong = $cumulative + $t * $segmentLength;
            }

            $cumulative += $segmentLength;
        }

        return ['distance' => $bestDistance, 'along' => $bestAlong];
    }

    private function projectionFactor(
        float $lat,
        float $lng,
        float $aLat,
        float $aLng,
        float $bLat,
        float $bLng,
    ): float {
        $cosLat = cos(deg2rad($lat));
        $bx = deg2rad($bLng - $aLng) * $cosLat;
        $by = deg2rad($bLat - $aLat);
        $px = deg2rad($lng - $aLng) * $cosLat;
        $py = deg2rad($lat - $aLat);

        $lengthSq = $bx * $bx + $by * $by;

        if ($lengthSq <= 1e-12) {
            return 0.0;
        }

        return max(0.0, min(1.0, ($px * $bx + $py * $by) / $lengthSq));
    }
}

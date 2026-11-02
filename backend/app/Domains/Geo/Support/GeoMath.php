<?php

namespace App\Domains\Geo\Support;

class GeoMath
{
    public const EARTH_RADIUS_M = 6371000.0;

    public static function haversineMeters(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
    ): float {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * atan2(sqrt($a), sqrt(1 - $a));
    }

    public static function bearingDegrees(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
    ): float {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dLng = deg2rad($lng2 - $lng1);

        $y = sin($dLng) * cos($phi2);
        $x = cos($phi1) * sin($phi2) - sin($phi1) * cos($phi2) * cos($dLng);

        return fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);
    }

    public static function cosTheta(float $bearingA, float $bearingB): float
    {
        return cos(deg2rad(self::angleDifference($bearingA, $bearingB)));
    }

    public static function angleDifference(float $a, float $b): float
    {
        $diff = fmod(abs($a - $b), 360.0);

        return $diff > 180.0 ? 360.0 - $diff : $diff;
    }

    /**
     * Perpendicular distance from a point to a segment, in metres, using a
     * local equirectangular projection (accurate enough at city scale).
     */
    public static function pointToSegmentMeters(
        float $lat,
        float $lng,
        float $aLat,
        float $aLng,
        float $bLat,
        float $bLng,
    ): float {
        $refLat = deg2rad($lat);
        $cosLat = cos($refLat);

        $ax = 0.0;
        $ay = 0.0;
        $bx = deg2rad($bLng - $aLng) * $cosLat * self::EARTH_RADIUS_M;
        $by = deg2rad($bLat - $aLat) * self::EARTH_RADIUS_M;
        $px = deg2rad($lng - $aLng) * $cosLat * self::EARTH_RADIUS_M;
        $py = deg2rad($lat - $aLat) * self::EARTH_RADIUS_M;

        $dx = $bx - $ax;
        $dy = $by - $ay;
        $lengthSq = $dx * $dx + $dy * $dy;

        if ($lengthSq <= 1e-9) {
            return sqrt($px * $px + $py * $py);
        }

        $t = max(0.0, min(1.0, (($px - $ax) * $dx + ($py - $ay) * $dy) / $lengthSq));
        $projX = $ax + $t * $dx;
        $projY = $ay + $t * $dy;

        return sqrt(($px - $projX) ** 2 + ($py - $projY) ** 2);
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $path
     */
    public static function pathLengthMeters(array $path): float
    {
        $total = 0.0;

        for ($i = 1; $i < count($path); $i++) {
            $total += self::haversineMeters($path[$i - 1][0], $path[$i - 1][1], $path[$i][0], $path[$i][1]);
        }

        return $total;
    }
}

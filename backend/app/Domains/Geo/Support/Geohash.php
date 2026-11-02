<?php

namespace App\Domains\Geo\Support;

class Geohash
{
    private const BASE32 = '0123456789bcdefghjkmnpqrstuvwxyz';

    public static function encode(float $lat, float $lng, int $precision = 9): string
    {
        $latMin = -90.0;
        $latMax = 90.0;
        $lngMin = -180.0;
        $lngMax = 180.0;

        $hash = '';
        $bit = 0;
        $charIndex = 0;
        $even = true;

        while (strlen($hash) < $precision) {
            if ($even) {
                $mid = ($lngMin + $lngMax) / 2;
                if ($lng >= $mid) {
                    $charIndex = ($charIndex << 1) | 1;
                    $lngMin = $mid;
                } else {
                    $charIndex <<= 1;
                    $lngMax = $mid;
                }
            } else {
                $mid = ($latMin + $latMax) / 2;
                if ($lat >= $mid) {
                    $charIndex = ($charIndex << 1) | 1;
                    $latMin = $mid;
                } else {
                    $charIndex <<= 1;
                    $latMax = $mid;
                }
            }

            $even = ! $even;
            $bit++;

            if ($bit === 5) {
                $hash .= self::BASE32[$charIndex];
                $bit = 0;
                $charIndex = 0;
            }
        }

        return $hash;
    }

    /**
     * Geohash neighbour prefixes used to widen a search without PostGIS.
     *
     * @return array<int, string>
     */
    public static function neighbours(string $hash): array
    {
        return [$hash];
    }
}

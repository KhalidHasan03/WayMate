<?php

namespace App\Domains\Geo\Services;

use App\Domains\Geo\Data\RouteResult;
use App\Domains\Geo\Support\GeoMath;
use App\Models\GeoRouteCache;

class RouteService
{
    public function __construct(
        private readonly GoogleDirectionsClient $google,
    ) {}

    public function route(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
    ): RouteResult {
        $hash = $this->hash($originLat, $originLng, $destLat, $destLng);
        $cached = GeoRouteCache::query()
            ->where('route_hash', $hash)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if ($cached) {
            return new RouteResult(
                distanceM: (int) $cached->distance_m,
                durationS: (int) $cached->duration_s,
                path: $cached->route_path ?? [[$originLat, $originLng], [$destLat, $destLng]],
                provider: $cached->provider,
                encodedPolyline: $cached->encoded_polyline,
            );
        }

        $result = $this->google->fetch($originLat, $originLng, $destLat, $destLng)
            ?? $this->fallback($originLat, $originLng, $destLat, $destLng);

        GeoRouteCache::query()->updateOrCreate(
            ['route_hash' => $hash],
            [
                'origin_lat' => $originLat,
                'origin_lng' => $originLng,
                'dest_lat' => $destLat,
                'dest_lng' => $destLng,
                'encoded_polyline' => $result->encodedPolyline,
                'route_path' => $result->path,
                'distance_m' => $result->distanceM,
                'duration_s' => $result->durationS,
                'provider' => $result->provider,
                'fetched_at' => now(),
                'expires_at' => now()->addDays((int) config('waymate.maps.cache_ttl_days', 30)),
            ],
        );

        return $result;
    }

    private function fallback(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
    ): RouteResult {
        $distance = (int) round(GeoMath::haversineMeters($originLat, $originLng, $destLat, $destLng));
        $segments = 24;
        $path = [];

        for ($i = 0; $i <= $segments; $i++) {
            $t = $i / $segments;
            $path[] = [
                $originLat + ($destLat - $originLat) * $t,
                $originLng + ($destLng - $originLng) * $t,
            ];
        }

        $duration = max(60, (int) ceil($distance / 7.0));

        return new RouteResult(
            distanceM: $distance,
            durationS: $duration,
            path: $path,
            provider: 'fallback',
        );
    }

    private function hash(float $originLat, float $originLng, float $destLat, float $destLng): string
    {
        return sha1(sprintf('%.5f,%.5f,%.5f,%.5f', $originLat, $originLng, $destLat, $destLng));
    }
}

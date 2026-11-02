<?php

namespace App\Domains\Geo\Services;

use App\Domains\Geo\Data\RouteResult;
use App\Domains\Geo\Support\PolylineCodec;
use Illuminate\Support\Facades\Http;

class GoogleDirectionsClient
{
    public function fetch(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
    ): ?RouteResult {
        $key = (string) config('waymate.maps.google_key');

        if ($key === '') {
            return null;
        }

        try {
            $response = Http::timeout(6)->get('https://maps.googleapis.com/maps/api/directions/json', [
                'origin' => "{$originLat},{$originLng}",
                'destination' => "{$destLat},{$destLng}",
                'mode' => 'driving',
                'departure_time' => 'now',
                'traffic_model' => 'best_guess',
                'key' => $key,
            ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->ok() || $response->json('status') !== 'OK') {
            return null;
        }

        $route = $response->json('routes.0');

        if (! is_array($route)) {
            return null;
        }

        $leg = $route['legs'][0] ?? [];
        $distance = (int) ($leg['distance']['value'] ?? 0);
        $duration = (int) ($leg['duration_in_traffic']['value'] ?? $leg['duration']['value'] ?? 0);

        if ($distance <= 0) {
            return null;
        }

        $encoded = $route['overview_polyline']['points'] ?? null;
        $path = $encoded ? PolylineCodec::decode($encoded) : [];

        if (count($path) < 2) {
            $path = [[$originLat, $originLng], [$destLat, $destLng]];
        }

        return new RouteResult(
            distanceM: $distance,
            durationS: max(60, $duration),
            path: $path,
            provider: 'google',
            encodedPolyline: $encoded,
        );
    }
}

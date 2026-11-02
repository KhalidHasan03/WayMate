<?php

namespace App\Domains\Geo\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeocodingService
{
    /**
     * A small offline gazetteer so location search still works without an
     * external provider. Coordinates are approximate city landmarks.
     */
    public const PRESETS = [
        ['label' => 'Mirpur 10, Dhaka', 'lat' => 23.8069, 'lng' => 90.3687],
        ['label' => 'Mirpur 1, Dhaka', 'lat' => 23.7956, 'lng' => 90.3536],
        ['label' => 'Kazipara, Dhaka', 'lat' => 23.7973, 'lng' => 90.3719],
        ['label' => 'Shewrapara, Dhaka', 'lat' => 23.7896, 'lng' => 90.3768],
        ['label' => 'Agargaon, Dhaka', 'lat' => 23.7780, 'lng' => 90.3795],
        ['label' => 'Farmgate, Dhaka', 'lat' => 23.7573, 'lng' => 90.3900],
        ['label' => 'Karwan Bazar, Dhaka', 'lat' => 23.7510, 'lng' => 90.3930],
        ['label' => 'Shahbagh, Dhaka', 'lat' => 23.7389, 'lng' => 90.3956],
        ['label' => 'Motijheel, Dhaka', 'lat' => 23.7330, 'lng' => 90.4170],
        ['label' => 'Dhanmondi 27, Dhaka', 'lat' => 23.7461, 'lng' => 90.3742],
        ['label' => 'Jhigatola, Dhaka', 'lat' => 23.7400, 'lng' => 90.3830],
        ['label' => 'Lalmatia, Dhaka', 'lat' => 23.7520, 'lng' => 90.3700],
        ['label' => 'Gulshan 1, Dhaka', 'lat' => 23.7806, 'lng' => 90.4150],
        ['label' => 'Gulshan 2, Dhaka', 'lat' => 23.7936, 'lng' => 90.4143],
        ['label' => 'Banani, Dhaka', 'lat' => 23.7936, 'lng' => 90.4043],
        ['label' => 'Mohakhali, Dhaka', 'lat' => 23.7770, 'lng' => 90.4050],
        ['label' => 'Uttara 3, Dhaka', 'lat' => 23.8687, 'lng' => 90.3950],
        ['label' => 'Uttara 10, Dhaka', 'lat' => 23.8750, 'lng' => 90.3790],
        ['label' => 'Hazrat Shahjalal Airport, Dhaka', 'lat' => 23.8510, 'lng' => 90.4050],
        ['label' => 'Badda, Dhaka', 'lat' => 23.7800, 'lng' => 90.4260],
        ['label' => 'Rampura, Dhaka', 'lat' => 23.7620, 'lng' => 90.4210],
        ['label' => 'Malibagh, Dhaka', 'lat' => 23.7460, 'lng' => 90.4110],
        ['label' => 'Khilgaon, Dhaka', 'lat' => 23.7510, 'lng' => 90.4260],
        ['label' => 'Bashundhara R/A, Dhaka', 'lat' => 23.8220, 'lng' => 90.4260],
        ['label' => 'Newmarket, Dhaka', 'lat' => 23.7350, 'lng' => 90.3840],
        ['label' => 'Sadarghat, Dhaka', 'lat' => 23.7080, 'lng' => 90.4070],
        ['label' => 'Savar, Dhaka', 'lat' => 23.8388, 'lng' => 90.2637],
        ['label' => 'Narayanganj', 'lat' => 23.6238, 'lng' => 90.4993],
        ['label' => 'Tongi, Gazipur', 'lat' => 23.8940, 'lng' => 90.4020],
        ['label' => 'Gazipur Chowrasta', 'lat' => 23.9999, 'lng' => 90.4203],
    ];

    /**
     * @return array<int, array{label: string, lat: float, lng: float, provider: string}>
     */
    public function search(string $query, int $limit = 6): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $key = 'geocode:'.sha1(mb_strtolower($query).'|'.$limit);

        return Cache::remember($key, now()->addDays(7), function () use ($query, $limit) {
            $results = $this->searchGoogle($query, $limit)
                ?? $this->searchNominatim($query, $limit)
                ?? [];

            if ($results === []) {
                $results = $this->searchPresets($query, $limit);
            }

            return $results;
        });
    }

    /**
     * @return array<int, array{label: string, lat: float, lng: float, provider: string}>|null
     */
    private function searchGoogle(string $query, int $limit): ?array
    {
        $key = (string) config('waymate.maps.google_key');

        if ($key === '') {
            return null;
        }

        try {
            $response = Http::timeout(6)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $query,
                'components' => 'country:BD',
                'key' => $key,
            ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->ok() || $response->json('status') !== 'OK') {
            return null;
        }

        $results = [];

        foreach (array_slice($response->json('results', []), 0, $limit) as $result) {
            $location = $result['geometry']['location'] ?? null;

            if (! $location) {
                continue;
            }

            $results[] = [
                'label' => $result['formatted_address'] ?? $query,
                'lat' => (float) $location['lat'],
                'lng' => (float) $location['lng'],
                'provider' => 'google',
            ];
        }

        return $results === [] ? null : $results;
    }

    /**
     * @return array<int, array{label: string, lat: float, lng: float, provider: string}>|null
     */
    private function searchNominatim(string $query, int $limit): ?array
    {
        if (config('waymate.geocoding.provider') !== 'nominatim') {
            return null;
        }

        try {
            $response = Http::timeout(6)
                ->withHeaders(['User-Agent' => config('waymate.geocoding.user_agent')])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'countrycodes' => 'bd',
                    'limit' => $limit,
                    'addressdetails' => 1,
                ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->ok() || ! is_array($response->json())) {
            return null;
        }

        $results = [];

        foreach ($response->json() as $result) {
            if (! isset($result['lat'], $result['lon'])) {
                continue;
            }

            $results[] = [
                'label' => $result['display_name'] ?? $query,
                'lat' => (float) $result['lat'],
                'lng' => (float) $result['lon'],
                'provider' => 'nominatim',
            ];
        }

        return $results === [] ? null : $results;
    }

    /**
     * @return array<int, array{label: string, lat: float, lng: float, provider: string}>
     */
    private function searchPresets(string $query, int $limit): array
    {
        $needle = mb_strtolower($query);

        $matches = array_values(array_filter(
            self::PRESETS,
            fn (array $place) => str_contains(mb_strtolower($place['label']), $needle),
        ));

        return array_slice(array_map(
            fn (array $place) => [...$place, 'provider' => 'preset'],
            $matches,
        ), 0, $limit);
    }
}

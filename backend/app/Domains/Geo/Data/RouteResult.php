<?php

namespace App\Domains\Geo\Data;

final class RouteResult
{
    /**
     * @param  array<int, array{0: float, 1: float}>  $path
     */
    public function __construct(
        public readonly int $distanceM,
        public readonly int $durationS,
        public readonly array $path,
        public readonly string $provider,
        public readonly ?string $encodedPolyline = null,
    ) {}

    public function toArray(): array
    {
        return [
            'distance_m' => $this->distanceM,
            'duration_s' => $this->durationS,
            'path' => $this->path,
            'provider' => $this->provider,
            'encoded_polyline' => $this->encodedPolyline,
        ];
    }
}

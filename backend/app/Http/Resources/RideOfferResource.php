<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RideOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'origin' => [
                'lat' => (float) $this->origin_lat,
                'lng' => (float) $this->origin_lng,
                'label' => $this->origin_label,
            ],
            'destination' => [
                'lat' => (float) $this->dest_lat,
                'lng' => (float) $this->dest_lng,
                'label' => $this->dest_label,
            ],
            'route_path' => $this->route_path,
            'distance_m' => (int) $this->distance_m,
            'duration_s' => (int) $this->duration_s,
            'depart_at' => $this->depart_at?->toIso8601String(),
            'depart_window_min' => (int) $this->depart_window_min,
            'seats_total' => (int) $this->seats_total,
            'seats_allocated' => (int) $this->seats_allocated,
            'seats_available' => $this->seatsAvailable(),
            'cost_total_poisha' => (int) $this->cost_total_poisha,
            'provider_participates' => (bool) $this->provider_participates,
            'women_only' => (bool) $this->women_only,
            'notes' => $this->notes,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'provider' => $this->whenLoaded('provider', fn () => new PublicUserResource($this->provider)),
            'vehicle' => $this->whenLoaded('vehicle', fn () => new VehicleResource($this->vehicle)),
            'created_at' => $this->created_at,
        ];
    }
}

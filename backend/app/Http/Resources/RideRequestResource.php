<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RideRequestResource extends JsonResource
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
            'seats_needed' => (int) $this->seats_needed,
            'pool_max_seats' => (int) $this->pool_max_seats,
            'accepting_copassengers' => (bool) $this->accepting_copassengers,
            'budget_per_seat_poisha' => $this->budget_per_seat_poisha !== null
                ? (int) $this->budget_per_seat_poisha
                : null,
            'women_only' => (bool) $this->women_only,
            'transport_preference' => $this->transport_preference,
            'notes' => $this->notes,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'creator' => $this->whenLoaded('creator', fn () => new PublicUserResource($this->creator)),
            'join_requests_count' => $this->whenCounted('joinRequests'),
            'join_requests' => JoinRequestResource::collection($this->whenLoaded('joinRequests')),
            'active_trip' => $this->whenLoaded('activeTrip', fn () => $this->activeTrip ? [
                'id' => $this->activeTrip->id,
                'status' => $this->activeTrip->status,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}

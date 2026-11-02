<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $source = $this->ride_offer_id !== null ? $this->rideOffer : $this->rideRequest;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'transport_mode' => $this->transport_mode,
            'source' => $this->ride_offer_id !== null ? 'offer' : 'request',
            'ride_request_id' => $this->ride_request_id,
            'ride_offer_id' => $this->ride_offer_id,
            'pool_capacity' => $this->pool_capacity !== null ? (int) $this->pool_capacity : null,
            'seats_allocated' => $this->seats_allocated !== null ? (int) $this->seats_allocated : null,
            'seats_in_trip' => $this->whenLoaded('participants', fn () => (int) $this->participants->sum('seats')),
            'meeting' => [
                'lat' => $this->meeting_lat !== null ? (float) $this->meeting_lat : null,
                'lng' => $this->meeting_lng !== null ? (float) $this->meeting_lng : null,
                'label' => $this->meeting_label,
            ],
            'origin' => $source ? [
                'lat' => (float) $source->origin_lat,
                'lng' => (float) $source->origin_lng,
                'label' => $source->origin_label,
            ] : null,
            'destination' => $source ? [
                'lat' => (float) $source->dest_lat,
                'lng' => (float) $source->dest_lng,
                'label' => $source->dest_label,
            ] : null,
            'depart_at' => $source?->depart_at?->toIso8601String(),
            'coordinator' => $this->whenLoaded('coordinator', fn () => [
                'id' => $this->coordinator->id,
                'name' => $this->coordinator->name,
            ]),
            'provider' => $this->whenLoaded('provider', fn () => $this->provider ? [
                'id' => $this->provider->id,
                'name' => $this->provider->name,
                'rating' => (float) $this->provider->rating_avg,
            ] : null),
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? new VehicleResource($this->vehicle) : null),
            'request' => $this->whenLoaded('rideRequest', fn () => $this->rideRequest
                ? new RideRequestResource($this->rideRequest)
                : null),
            'offer' => $this->whenLoaded('rideOffer', fn () => $this->rideOffer
                ? new RideOfferResource($this->rideOffer)
                : null),
            'participants' => TripParticipantResource::collection($this->whenLoaded('participants')),
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
            'cancel_reason' => $this->cancel_reason,
            'tracking_enabled' => (bool) $this->tracking_enabled,
            'tracking_started_at' => $this->tracking_started_at?->toIso8601String(),
            'tracking_stopped_at' => $this->tracking_stopped_at?->toIso8601String(),
            'created_at' => $this->created_at,
        ];
    }
}

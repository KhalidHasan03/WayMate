<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JoinRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'seats' => (int) $this->seats,
            'status' => $this->status,
            'message' => $this->message,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'rating' => (float) $this->user->rating_avg,
                'trips_completed' => (int) $this->user->trips_completed,
            ]),
            'consented_contribution_poisha' => $this->consented_contribution_poisha !== null
                ? (int) $this->consented_contribution_poisha
                : null,
            'start' => [
                'lat' => $this->start_lat !== null ? (float) $this->start_lat : null,
                'lng' => $this->start_lng !== null ? (float) $this->start_lng : null,
            ],
            'intended_depart_at' => $this->intended_depart_at?->toIso8601String(),
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? [
                'id' => $this->vehicle->id,
                'make' => $this->vehicle->make,
                'model' => $this->vehicle->model,
                'registration_no' => $this->vehicle->registration_no,
            ] : null),
            'ride_request' => $this->whenLoaded('rideRequest', fn () => $this->rideRequest ? [
                'id' => $this->rideRequest->id,
                'status' => $this->rideRequest->status,
                'origin' => [
                    'lat' => (float) $this->rideRequest->origin_lat,
                    'lng' => (float) $this->rideRequest->origin_lng,
                    'label' => $this->rideRequest->origin_label,
                ],
                'destination' => [
                    'lat' => (float) $this->rideRequest->dest_lat,
                    'lng' => (float) $this->rideRequest->dest_lng,
                    'label' => $this->rideRequest->dest_label,
                ],
                'depart_at' => $this->rideRequest->depart_at?->toIso8601String(),
                'seats_needed' => (int) $this->rideRequest->seats_needed,
            ] : null),
            'ride_offer' => $this->whenLoaded('rideOffer', fn () => $this->rideOffer ? [
                'id' => $this->rideOffer->id,
                'status' => $this->rideOffer->status,
                'origin' => [
                    'lat' => (float) $this->rideOffer->origin_lat,
                    'lng' => (float) $this->rideOffer->origin_lng,
                    'label' => $this->rideOffer->origin_label,
                ],
                'destination' => [
                    'lat' => (float) $this->rideOffer->dest_lat,
                    'lng' => (float) $this->rideOffer->dest_lng,
                    'label' => $this->rideOffer->dest_label,
                ],
                'depart_at' => $this->rideOffer->depart_at?->toIso8601String(),
                'seats_available' => $this->rideOffer->seatsAvailable(),
                'cost_total_poisha' => (int) $this->rideOffer->cost_total_poisha,
            ] : null),
            'decided_at' => $this->decided_at,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
        ];
    }
}

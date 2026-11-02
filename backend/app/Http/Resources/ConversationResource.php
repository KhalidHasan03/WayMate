<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $trip = $this->whenLoaded('trip');
        $source = $this->trip?->ride_offer_id !== null
            ? $this->trip?->rideOffer
            : $this->trip?->rideRequest;

        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'unread_count' => $this->unread_count ?? 0,
            'last_read_message_id' => $this->last_read_message_id ?? null,
            'trip' => $trip ? [
                'id' => $this->trip->id,
                'status' => $this->trip->status,
                'source' => $this->trip->ride_offer_id !== null ? 'offer' : 'request',
                'origin' => $source ? [
                    'label' => $source->origin_label,
                    'lat' => (float) $source->origin_lat,
                    'lng' => (float) $source->origin_lng,
                ] : null,
                'destination' => $source ? [
                    'label' => $source->dest_label,
                    'lat' => (float) $source->dest_lat,
                    'lng' => (float) $source->dest_lng,
                ] : null,
                'depart_at' => $source?->depart_at?->toIso8601String(),
            ] : null,
            'participants' => $this->whenLoaded('participants', fn () => $this->participants->map(
                fn ($participant) => [
                    'id' => $participant->user_id,
                    'name' => $participant->user?->name,
                    'avatar_url' => $participant->user?->avatar_path
                        ? asset('storage/'.$participant->user->avatar_path)
                        : null,
                ],
            )),
            'latest_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage
                ? new MessageResource($this->latestMessage->loadMissing('sender'))
                : null),
            'created_at' => $this->created_at,
        ];
    }
}

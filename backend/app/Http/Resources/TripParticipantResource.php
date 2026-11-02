<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'seats' => (int) $this->seats,
            'status' => $this->status,
            'consented_fare_version' => (int) $this->consented_fare_version,
            'joined_at' => $this->joined_at,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar_url' => $this->user->avatar_path ? asset('storage/'.$this->user->avatar_path) : null,
                'rating' => (float) $this->user->rating_avg,
                'trips_completed' => (int) $this->user->trips_completed,
            ]),
        ];
    }
}

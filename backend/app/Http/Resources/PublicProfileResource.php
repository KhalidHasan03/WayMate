<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...(new PublicUserResource($this->resource))->resolve($request),
            'bio' => $this->whenLoaded('profile', fn () => $this->profile?->bio),
            'preferred_vehicle_type' => $this->whenLoaded('profile', fn () => $this->profile?->preferred_vehicle_type),
            'rating_count' => (int) $this->rating_count,
        ];
    }
}

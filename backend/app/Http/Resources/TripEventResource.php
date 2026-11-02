<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'meta' => $this->meta,
            'actor' => $this->whenLoaded('actor', fn () => $this->actor
                ? new PublicUserResource($this->actor)
                : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

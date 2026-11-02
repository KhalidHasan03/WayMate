<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'lat' => (float) $this->lat,
            'lng' => (float) $this->lng,
            'accuracy_m' => $this->accuracy_m !== null ? (float) $this->accuracy_m : null,
            'speed_mps' => $this->speed_mps !== null ? (float) $this->speed_mps : null,
            'heading_deg' => $this->heading_deg !== null ? (float) $this->heading_deg : null,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
        ];
    }
}

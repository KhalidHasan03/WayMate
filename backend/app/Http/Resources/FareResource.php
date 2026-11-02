<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FareResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'status' => $this->status,
            'fare_version' => (int) $this->fare_version,
            'estimated_total_poisha' => (int) $this->estimated_total_poisha,
            'agreed_total_poisha' => $this->agreed_total_poisha !== null ? (int) $this->agreed_total_poisha : null,
            'actual_total_poisha' => $this->actual_total_poisha !== null ? (int) $this->actual_total_poisha : null,
            'participant_count' => (int) $this->participant_count,
            'provider_participates' => (bool) $this->provider_participates,
            'rounding_absorbed_poisha' => (int) $this->rounding_absorbed_poisha,
            'collector' => $this->whenLoaded('collector', fn () => $this->collector ? [
                'id' => $this->collector->id,
                'name' => $this->collector->name,
            ] : null),
            'items' => FareItemResource::collection($this->whenLoaded('items')),
            'contributions' => ContributionResource::collection($this->whenLoaded('contributions', fn () => $this->contributions
                ->where('fare_version', $this->fare_version)
                ->values())),
            'adjustments' => FareAdjustmentResource::collection($this->whenLoaded('adjustments')),
            'agreed_at' => $this->agreed_at,
            'settled_at' => $this->settled_at,
            'created_at' => $this->created_at,
        ];
    }
}

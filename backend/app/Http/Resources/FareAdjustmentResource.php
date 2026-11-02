<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FareAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fare_version' => (int) $this->fare_version,
            'new_total_poisha' => (int) $this->new_total_poisha,
            'reason' => $this->reason,
            'status' => $this->status,
            'proposer' => $this->whenLoaded('proposer', fn () => [
                'id' => $this->proposer->id,
                'name' => $this->proposer->name,
            ]),
            'votes' => $this->whenLoaded('votes', fn () => $this->votes->map(fn ($vote) => [
                'user' => $vote->user ? [
                    'id' => $vote->user->id,
                    'name' => $vote->user->name,
                ] : null,
                'vote' => $vote->vote,
                'voted_at' => $vote->voted_at,
            ])),
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
        ];
    }
}

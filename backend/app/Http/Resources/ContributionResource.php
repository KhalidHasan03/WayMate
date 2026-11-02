<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'kind' => $this->kind,
            'amount_poisha' => (int) $this->amount_poisha,
            'status' => $this->status,
            'note' => $this->note,
            'settled_at' => $this->settled_at,
        ];
    }
}

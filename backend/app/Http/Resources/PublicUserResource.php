<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->avatar_path
                ? asset('storage/'.$this->avatar_path)
                : null,
            'gender' => $this->gender,
            'rating' => (float) $this->rating_avg,
            'trips_completed' => (int) $this->trips_completed,
            'no_shows' => (int) $this->no_show_count,
            'identity_verified' => $this->hasVerifiedIdentity(),
            'phone_verified' => $this->phone_verified_at !== null,
            'member_since' => $this->created_at?->toIso8601String(),
        ];
    }
}

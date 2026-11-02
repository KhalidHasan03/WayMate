<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'gender' => $this->gender,
            'dob' => $this->dob?->toDateString(),
            'avatar_url' => $this->avatar_path ? asset('storage/'.$this->avatar_path) : null,
            'locale' => $this->locale,
            'status' => $this->status,
            'phone_verified_at' => $this->phone_verified_at,
            'identity_verified' => $this->hasVerifiedIdentity(),
            'rating' => [
                'average' => (float) $this->rating_avg,
                'count' => (int) $this->rating_count,
                'trips_completed' => (int) $this->trips_completed,
                'no_shows' => (int) $this->no_show_count,
            ],
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'profile' => $this->whenLoaded('profile', fn () => [
                'bio' => $this->profile?->bio,
                'preferred_vehicle_type' => $this->profile?->preferred_vehicle_type,
                'women_only_opt_in' => (bool) $this->profile?->women_only_opt_in,
                'notify_prefs' => $this->profile?->notify_prefs,
                'terms_accepted_at' => $this->profile?->terms_accepted_at,
                'cost_share_disclaimer_at' => $this->profile?->cost_share_disclaimer_at,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}

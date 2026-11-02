<?php

namespace App\Policies;

use App\Models\JoinRequest;
use App\Models\User;

class JoinRequestPolicy
{
    public function view(User $user, JoinRequest $join): bool
    {
        return $user->id === $join->user_id || $this->isTargetOwner($user, $join);
    }

    public function accept(User $user, JoinRequest $join): bool
    {
        return $this->isTargetOwner($user, $join);
    }

    public function decline(User $user, JoinRequest $join): bool
    {
        return $this->isTargetOwner($user, $join);
    }

    public function withdraw(User $user, JoinRequest $join): bool
    {
        return $user->id === $join->user_id;
    }

    private function isTargetOwner(User $user, JoinRequest $join): bool
    {
        if ($join->ride_offer_id !== null) {
            return $join->rideOffer?->provider_id === $user->id;
        }

        return $join->rideRequest?->creator_id === $user->id;
    }
}

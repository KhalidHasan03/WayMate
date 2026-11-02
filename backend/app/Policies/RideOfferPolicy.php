<?php

namespace App\Policies;

use App\Models\RideOffer;
use App\Models\User;

class RideOfferPolicy
{
    public function view(User $user, RideOffer $rideOffer): bool
    {
        return $rideOffer->status === RideOffer::STATUS_OPEN || $user->id === $rideOffer->provider_id;
    }

    public function update(User $user, RideOffer $rideOffer): bool
    {
        return $user->id === $rideOffer->provider_id;
    }

    public function cancel(User $user, RideOffer $rideOffer): bool
    {
        return $user->id === $rideOffer->provider_id;
    }
}

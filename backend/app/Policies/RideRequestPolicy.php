<?php

namespace App\Policies;

use App\Models\RideRequest;
use App\Models\User;

class RideRequestPolicy
{
    public function view(User $user, RideRequest $rideRequest): bool
    {
        return $user->id === $rideRequest->creator_id;
    }

    public function update(User $user, RideRequest $rideRequest): bool
    {
        return $user->id === $rideRequest->creator_id;
    }

    public function cancel(User $user, RideRequest $rideRequest): bool
    {
        return $user->id === $rideRequest->creator_id;
    }
}

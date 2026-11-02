<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function view(User $user, Trip $trip): bool
    {
        if ($user->id === $trip->coordinator_id || $user->id === $trip->provider_id) {
            return true;
        }

        return $trip->participants()->where('user_id', $user->id)->exists();
    }

    public function manageFare(User $user, Trip $trip): bool
    {
        return $user->id === $trip->coordinator_id
            && in_array($trip->status, [
                Trip::STATUS_FORMING,
                Trip::STATUS_READY,
                Trip::STATUS_IN_PROGRESS,
                Trip::STATUS_COMPLETED,
            ], true);
    }

    public function settleFare(User $user, Trip $trip): bool
    {
        return $user->id === $trip->coordinator_id || $user->id === $trip->provider_id;
    }

    public function manageLifecycle(User $user, Trip $trip): bool
    {
        return $user->id === $trip->coordinator_id || $user->id === $trip->provider_id;
    }
}

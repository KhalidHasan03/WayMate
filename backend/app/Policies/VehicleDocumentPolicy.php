<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleDocument;

class VehicleDocumentPolicy
{
    public function view(User $user, VehicleDocument $document): bool
    {
        return $user->id === $document->vehicle?->user_id
            || $user->hasPermissionTo('verification.review');
    }

    public function delete(User $user, VehicleDocument $document): bool
    {
        return $user->id === $document->vehicle?->user_id
            && $document->status === VehicleDocument::STATUS_PENDING;
    }
}

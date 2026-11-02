<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VerificationDocument;

class VerificationDocumentPolicy
{
    public function view(User $user, VerificationDocument $document): bool
    {
        return $user->id === $document->user_id || $user->hasPermissionTo('verification.review');
    }

    public function delete(User $user, VerificationDocument $document): bool
    {
        return $user->id === $document->user_id && $document->status === VerificationDocument::STATUS_PENDING;
    }
}

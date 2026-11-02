<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripParticipant extends Model
{
    public const STATUS_CONSENT_PENDING = 'consent_pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CHECKED_IN = 'checked_in';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_CANCELLED = 'cancelled';

    public const ROLE_PASSENGER = 'passenger';

    public const ROLE_PROVIDER = 'provider';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConfirmed(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_CHECKED_IN, self::STATUS_COMPLETED], true);
    }

    public function isProvider(): bool
    {
        return $this->role === self::ROLE_PROVIDER;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Trip extends Model
{
    public const STATUS_FORMING = 'forming';

    public const STATUS_READY = 'ready';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const MODE_UNDECIDED = 'undecided';

    public const MODE_EXTERNAL = 'external_ride';

    public const MODE_PRIVATE = 'private_vehicle';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meeting_lat' => 'float',
            'meeting_lng' => 'float',
            'tracking_enabled' => 'boolean',
            'tracking_started_at' => 'datetime',
            'tracking_stopped_at' => 'datetime',
        ];
    }

    public function rideRequest(): BelongsTo
    {
        return $this->belongsTo(RideRequest::class);
    }

    public function rideOffer(): BelongsTo
    {
        return $this->belongsTo(RideOffer::class);
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TripParticipant::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TripEvent::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(TripLocation::class);
    }

    public function fare(): HasOne
    {
        return $this->hasOne(TripFare::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function isRequestSourced(): bool
    {
        return $this->ride_request_id !== null;
    }

    public function isOfferSourced(): bool
    {
        return $this->ride_offer_id !== null;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_FORMING, self::STATUS_READY, self::STATUS_IN_PROGRESS], true);
    }

    public function hasCapacityFor(int $seats): bool
    {
        if (! $this->isRequestSourced()) {
            return false;
        }

        return ((int) $this->seats_allocated + $seats) <= (int) $this->pool_capacity;
    }
}

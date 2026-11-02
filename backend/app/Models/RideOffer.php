<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class RideOffer extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'depart_at' => 'datetime',
            'expires_at' => 'datetime',
            'route_path' => 'array',
            'origin_lat' => 'float',
            'origin_lng' => 'float',
            'dest_lat' => 'float',
            'dest_lng' => 'float',
            'provider_participates' => 'boolean',
            'women_only' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(JoinRequest::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function activeTrip(): HasOne
    {
        return $this->hasOne(Trip::class)
            ->whereIn('status', [Trip::STATUS_FORMING, Trip::STATUS_READY, Trip::STATUS_IN_PROGRESS]);
    }

    public function seatsAvailable(): int
    {
        return max(0, (int) $this->seats_total - (int) $this->seats_allocated);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN && $this->seatsAvailable() > 0;
    }
}

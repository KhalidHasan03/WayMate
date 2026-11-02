<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class RideRequest extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const PREF_ANY = 'any';

    public const PREF_EXTERNAL = 'external';

    public const PREF_PRIVATE = 'private';

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
            'accepting_copassengers' => 'boolean',
            'women_only' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
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

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function totalSeatsNeeded(): int
    {
        return (int) $this->seats_needed;
    }
}

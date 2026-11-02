<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripFare extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_AGREEING = 'agreeing';

    public const STATUS_AGREED = 'agreed';

    public const STATUS_ADJUSTMENT_PENDING = 'adjustment_pending';

    public const STATUS_SETTLED = 'settled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'agreed_at' => 'datetime',
            'settled_at' => 'datetime',
            'provider_participates' => 'boolean',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FareItem::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(FareAdjustment::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class, 'trip_id', 'trip_id');
    }

    public function isAgreed(): bool
    {
        return in_array($this->status, [self::STATUS_AGREED, self::STATUS_ADJUSTMENT_PENDING, self::STATUS_SETTLED], true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FareItem extends Model
{
    public const SPLIT_EQUAL = 'equal';

    public const SPLIT_PROVIDER_ONLY = 'provider_only';

    protected $guarded = ['id'];

    public function tripFare(): BelongsTo
    {
        return $this->belongsTo(TripFare::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

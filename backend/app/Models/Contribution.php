<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contribution extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'cash_paid';

    public const STATUS_WAIVED = 'waived';

    public const STATUS_VOID = 'void';

    public const KIND_SHARE = 'share';

    public const KIND_TOLL = 'toll';

    public const KIND_EXTRA = 'extra';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'settled_at' => 'datetime',
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

    public function fareItem(): BelongsTo
    {
        return $this->belongsTo(FareItem::class);
    }

    public function settler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }
}

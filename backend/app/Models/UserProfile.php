<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'notify_prefs' => 'array',
            'women_only_opt_in' => 'boolean',
            'terms_accepted_at' => 'datetime',
            'cost_share_disclaimer_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

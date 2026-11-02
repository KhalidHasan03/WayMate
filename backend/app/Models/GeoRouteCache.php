<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeoRouteCache extends Model
{
    protected $table = 'geo_route_cache';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'route_path' => 'array',
            'origin_lat' => 'float',
            'origin_lng' => 'float',
            'dest_lat' => 'float',
            'dest_lng' => 'float',
            'fetched_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}

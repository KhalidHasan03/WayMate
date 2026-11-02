<?php

namespace Tests\Feature;

use App\Domains\Geo\Services\RouteService;
use App\Domains\Geo\Support\Geohash;
use App\Domains\Geo\Support\GeoMath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeoRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_fallback_route_returns_geometry_and_is_cached(): void
    {
        $service = app(RouteService::class);

        $result = $service->route(23.8103, 90.4125, 23.7330, 90.3950);

        $this->assertSame('fallback', $result->provider);
        $this->assertGreaterThan(5000, $result->distanceM);
        $this->assertGreaterThanOrEqual(60, $result->durationS);
        $this->assertCount(25, $result->path);

        $this->assertDatabaseCount('geo_route_cache', 1);

        $again = $service->route(23.8103, 90.4125, 23.7330, 90.3950);

        $this->assertSame($result->distanceM, $again->distanceM);
        $this->assertDatabaseCount('geo_route_cache', 1);
    }

    public function test_geohash_and_geo_math_helpers(): void
    {
        $hash = Geohash::encode(23.8103, 90.4125);

        $this->assertSame(9, strlen($hash));
        $this->assertStringStartsWith('w', $hash);

        $distance = GeoMath::haversineMeters(23.8103, 90.4125, 23.7330, 90.3950);
        $this->assertGreaterThan(8000, $distance);
        $this->assertLessThan(10000, $distance);

        $bearing = GeoMath::bearingDegrees(23.8103, 90.4125, 23.7330, 90.3950);
        $this->assertGreaterThan(180, $bearing);
        $this->assertLessThan(220, $bearing);

        $perpendicular = GeoMath::pointToSegmentMeters(
            23.80,
            90.40,
            23.81,
            90.39,
            23.79,
            90.41,
        );
        $this->assertLessThan(200, $perpendicular);
    }
}

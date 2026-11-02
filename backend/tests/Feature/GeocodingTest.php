<?php

namespace Tests\Feature;

use App\Domains\Geo\Services\GeocodingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GeocodingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Cache::flush();
    }

    public function test_nominatim_results_are_normalized(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'display_name' => 'Mirpur 10, Dhaka, Bangladesh',
                    'lat' => '23.8069',
                    'lon' => '90.3687',
                ],
            ]),
        ]);

        $results = app(GeocodingService::class)->search('Mirpur 10');

        $this->assertCount(1, $results);
        $this->assertSame('nominatim', $results[0]['provider']);
        $this->assertSame(23.8069, $results[0]['lat']);
    }

    public function test_presets_are_used_when_provider_fails(): void
    {
        Http::fake(['*' => Http::response('unavailable', 503)]);

        $results = app(GeocodingService::class)->search('Mirpur');

        $this->assertNotEmpty($results);
        $this->assertSame('preset', $results[0]['provider']);
        $this->assertStringContainsString('Mirpur', $results[0]['label']);
    }

    public function test_search_endpoint_requires_auth_and_query(): void
    {
        $this->getJson('/api/v1/geo/search?q=Mirpur')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        Http::fake(['*' => Http::response('unavailable', 503)]);

        $this->getJson('/api/v1/geo/search?q=Mirpur')
            ->assertOk()
            ->assertJsonPath('data.0.provider', 'preset');

        $this->getJson('/api/v1/geo/search?q=')->assertUnprocessable();
    }
}

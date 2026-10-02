<?php

namespace Tests\Feature\Api;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EmissionsDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_it_returns_only_the_requested_published_emissions_slice(): void
    {
        $response = $this->getJson('/api/emissions/dashboard?gas=kyotoghg&year_from=2023&year_to=2023&countries=BRA,NGA');

        $response
            ->assertOk()
            ->assertHeader('x-cache-ttl', '900')
            ->assertJsonStructure(['records' => [['country', 'sector', 'gas', 'year', 'value']]]);

        foreach ($response->json('records') as $record) {
            $this->assertContains($record['country'], ['BRA', 'NGA']);
            $this->assertSame('kyotoghg', $record['gas']);
            $this->assertSame(2023, $record['year']);
        }

        $cacheControl = $response->headers->get('cache-control');
        $this->assertStringContainsString('max-age=900', $cacheControl);
        $this->assertStringContainsString('stale-while-revalidate=900', $cacheControl);
    }

    public function test_it_returns_every_published_sector_for_the_country_matrix_year(): void
    {
        $response = $this->getJson('/api/emissions/dashboard?gas=kyotoghg&year_from=2023&year_to=2023');

        $response->assertOk();

        $records = collect($response->json('records'));

        $this->assertSame(['agriculture', 'energy', 'waste'], $records->pluck('sector')->unique()->sort()->values()->all());
        $this->assertSame(['BRA', 'GHA', 'IND', 'KEN', 'NGA', 'ZAF'], $records->pluck('country')->unique()->sort()->values()->all());
        $this->assertCount(18, $records);
    }

    public function test_country_filters_are_not_truncated_after_twelve_codes(): void
    {
        $codes = [...array_map(fn (int $index): string => 'X'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), range(1, 12)), 'BRA'];

        $response = $this->getJson('/api/emissions/dashboard?gas=kyotoghg&year_from=2023&year_to=2023&countries='.implode(',', $codes));

        $response->assertOk();
        $this->assertContains('BRA', collect($response->json('records'))->pluck('country')->all());
    }

    public function test_it_rejects_an_invalid_emissions_query(): void
    {
        $this->getJson('/api/emissions/dashboard?gas=kyotoghg&year_from=2023&year_to=2022')
            ->assertUnprocessable();
    }

    public function test_it_has_the_lookup_throttle(): void
    {
        $route = Route::getRoutes()->match(Request::create('/api/emissions/dashboard', 'GET'));

        $this->assertContains('throttle:lookups', $route->gatherMiddleware());
    }
}

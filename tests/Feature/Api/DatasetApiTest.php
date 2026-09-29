<?php

namespace Tests\Feature\Api;

use App\Models\AdminPeriodIndicatorValue;
use App\Models\Dataset;
use App\Models\DatasetVersion;
use App\Models\Geography;
use App\Models\Indicator;
use App\Models\Source;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DatasetApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_it_lists_api_enabled_datasets(): void
    {
        $response = $this->getJson('/api/datasets');

        $response
            ->assertOk()
            ->assertJsonFragment(['code' => 'climatewatch_historical_emissions'])
            ->assertJsonFragment(['code' => 'nigeria_flood_event_impacts'])
            ->assertJsonFragment(['api_enabled' => true]);

        $this->assertNotEmpty($response->json('data'));

        foreach ($response->json('data') as $dataset) {
            $this->assertTrue($dataset['api_enabled']);
        }
    }

    public function test_it_shows_dataset_details_with_versions(): void
    {
        $response = $this->getJson('/api/datasets/nigeria_country_year_indicators');

        $response
            ->assertOk()
            ->assertJsonPath('data.code', 'nigeria_country_year_indicators')
            ->assertJsonPath('data.latest_version.label', 'sample-v1')
            ->assertJsonPath('data.latest_version.row_count', 2)
            ->assertJsonCount(1, 'data.versions');
    }

    public function test_it_does_not_expose_api_disabled_dataset_details(): void
    {
        Dataset::query()
            ->where('code', 'nigeria_country_year_indicators')
            ->update(['api_enabled' => false]);

        $this->getJson('/api/datasets/nigeria_country_year_indicators')
            ->assertNotFound();
    }

    public function test_it_does_not_expose_api_disabled_dataset_records_or_exports(): void
    {
        Dataset::query()
            ->where('code', 'climatewatch_historical_emissions')
            ->update(['api_enabled' => false]);

        $this->getJson('/api/datasets/climatewatch_historical_emissions/records')
            ->assertNotFound();

        $this->getJson('/api/datasets/climatewatch_historical_emissions/export.json')
            ->assertNotFound();

        $this->get('/api/datasets/climatewatch_historical_emissions/export.csv')
            ->assertNotFound();
    }

    public function test_it_does_not_expose_non_active_datasets(): void
    {
        Dataset::query()
            ->where('code', 'nigeria_country_year_indicators')
            ->update(['status' => 'draft']);

        $this->getJson('/api/datasets')
            ->assertOk()
            ->assertJsonMissing(['code' => 'nigeria_country_year_indicators']);

        $this->getJson('/api/datasets/nigeria_country_year_indicators')
            ->assertNotFound();
    }

    public function test_it_returns_lookup_payloads(): void
    {
        $response = $this->getJson('/api/lookups');

        $response
            ->assertOk()
            ->assertJsonFragment(['code' => 'agriculture'])
            ->assertJsonFragment(['code' => 'ndc'])
            ->assertJsonFragment(['code' => 'co2'])
            ->assertJsonFragment(['code' => 'country_document'])
            ->assertJsonPath('meta.cache_seconds', 300)
            ->assertJsonPath('meta.sections.geographies.paginated', true)
            ->assertJsonPath('meta.sections.indicators.paginated', true)
            ->assertJsonPath('meta.sections.sources.paginated', true);
    }

    public function test_lookup_endpoint_has_cache_headers_and_route_throttle(): void
    {
        $route = Route::getRoutes()->match(Request::create('/api/lookups', 'GET'));

        $this->assertContains('throttle:lookups', $route->gatherMiddleware());

        $response = $this->getJson('/api/lookups');

        $response
            ->assertOk()
            ->assertHeader('x-cache-ttl', '300');

        $cacheControl = $response->headers->get('cache-control');

        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);
        $this->assertStringContainsString('stale-while-revalidate=300', $cacheControl);
    }

    public function test_it_caps_high_cardinality_lookup_sections_by_default(): void
    {
        for ($i = 1; $i <= 120; $i++) {
            Geography::query()->create([
                'code' => sprintf('NGT%03d', $i),
                'name' => sprintf('Test Geography %03d', $i),
                'country_code' => 'NG',
                'level' => 2,
                'type' => 'test',
            ]);

            Indicator::query()->create([
                'code' => sprintf('test_indicator_%03d', $i),
                'name' => sprintf('Test Indicator %03d', $i),
                'grain' => 'test',
            ]);
        }

        $response = $this->getJson('/api/lookups');

        $response
            ->assertOk()
            ->assertJsonCount(100, 'data.geographies')
            ->assertJsonCount(100, 'data.indicators')
            ->assertJsonPath('meta.sections.geographies.per_page', 100)
            ->assertJsonPath('meta.sections.geographies.current_page', 1)
            ->assertJsonPath('meta.sections.geographies.truncated', true)
            ->assertJsonPath('meta.sections.indicators.per_page', 100)
            ->assertJsonPath('meta.sections.indicators.current_page', 1)
            ->assertJsonPath('meta.sections.indicators.truncated', true);
    }

    public function test_it_caps_low_cardinality_database_lookup_sections_too(): void
    {
        for ($i = 1; $i <= 120; $i++) {
            Source::query()->create([
                'code' => sprintf('test_source_%03d', $i),
                'name' => sprintf('Test Source %03d', $i),
            ]);
        }

        $response = $this->getJson('/api/lookups');

        $response
            ->assertOk()
            ->assertJsonCount(100, 'data.sources')
            ->assertJsonPath('meta.sections.sources.per_page', 100)
            ->assertJsonPath('meta.sections.sources.current_page', 1)
            ->assertJsonPath('meta.sections.sources.truncated', true);
    }

    public function test_it_returns_typed_paginated_lookup_sections(): void
    {
        for ($i = 1; $i <= 60; $i++) {
            Geography::query()->create([
                'code' => sprintf('NGP%03d', $i),
                'name' => sprintf('Paged Geography %03d', $i),
                'country_code' => 'NG',
                'level' => 2,
                'type' => 'test',
            ]);
        }

        $expectedTotal = Geography::query()->count();
        $expectedPageCount = max(0, min(50, $expectedTotal - 50));
        $response = $this->getJson('/api/lookups?type=geographies&page=2&per_page=50');

        $response
            ->assertOk()
            ->assertJsonCount($expectedPageCount, 'data.geographies')
            ->assertJsonPath('meta.type', 'geographies')
            ->assertJsonPath('meta.sections.geographies.current_page', 2)
            ->assertJsonPath('meta.sections.geographies.per_page', 50)
            ->assertJsonPath('meta.sections.geographies.total', $expectedTotal);

        $this->assertArrayNotHasKey('sources', $response->json('data'));
    }

    public function test_it_rejects_unsupported_lookup_types(): void
    {
        $this->getJson('/api/lookups?type=users')
            ->assertUnprocessable();

        $this->getJson('/api/lookups?type[]=users')
            ->assertUnprocessable();

        $this->getJson('/api/lookups?type[foo]=users')
            ->assertUnprocessable();

        $this->getJson('/api/lookups?page[]=1')
            ->assertUnprocessable();
    }

    public function test_it_returns_filtered_admin_period_records(): void
    {
        $dataset = Dataset::query()->where('code', 'nigeria_rainfall_subnational')->firstOrFail();
        $version = $dataset->versions()->whereIn('status', ['published', 'active'])->latest('activated_at')->firstOrFail();
        $expectedCount = AdminPeriodIndicatorValue::query()
            ->where('dataset_version_id', $version->id)
            ->where('geography_code', 'NG002')
            ->where('period_type', 'dekad')
            ->count();
        $response = $this->getJson('/api/datasets/nigeria_rainfall_subnational/records?geography_code=NG002&period_type=dekad');

        $response
            ->assertOk()
            ->assertJsonPath('meta.dataset_code', 'nigeria_rainfall_subnational')
            ->assertJsonPath('meta.fact_table', 'admin_period_indicator_values')
            ->assertJsonCount($expectedCount, 'data')
            ->assertJsonFragment(['geography_code' => 'NG002'])
            ->assertJsonFragment(['period_type' => 'dekad']);
    }

    public function test_it_does_not_expose_draft_dataset_versions(): void
    {
        $dataset = Dataset::query()
            ->where('code', 'climatewatch_historical_emissions')
            ->firstOrFail();

        DatasetVersion::query()->create([
            'dataset_id' => $dataset->id,
            'version_label' => 'draft-only',
            'status' => 'draft',
            'row_count' => 0,
        ]);

        $this->getJson('/api/datasets/climatewatch_historical_emissions/records?version=draft-only')
            ->assertNotFound();
    }

    public function test_it_returns_filtered_country_document_records(): void
    {
        $response = $this->getJson('/api/datasets/nigeria_climate_policy_documents/records?status=published&document_type_code=ndc');

        $response
            ->assertOk()
            ->assertJsonPath('meta.dataset_code', 'nigeria_climate_policy_documents')
            ->assertJsonPath('meta.fact_table', 'country_documents')
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['document_code' => 'nga-ndc-2021'])
            ->assertJsonFragment(['document_type_code' => 'ndc']);
    }

    public function test_it_returns_filtered_event_impact_records(): void
    {
        $response = $this->getJson('/api/datasets/nigeria_flood_event_impacts/records?event_type=flood&geography_code=NG002');

        $response
            ->assertOk()
            ->assertJsonPath('meta.dataset_code', 'nigeria_flood_event_impacts')
            ->assertJsonPath('meta.fact_table', 'event_impacts')
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['metric_code' => 'people_displaced'])
            ->assertJsonFragment(['geography_code' => 'NG002']);
    }

    public function test_it_exports_filtered_dataset_records_as_json_with_metadata(): void
    {
        $response = $this->getJson('/api/datasets/climatewatch_historical_emissions/export.json?sector_code=agriculture&gas_code=kyotoghg');

        $response
            ->assertOk()
            ->assertJsonPath('meta.dataset_code', 'climatewatch_historical_emissions')
            ->assertJsonPath('meta.version_label', 'sample-v1')
            ->assertJsonPath('meta.fact_table', 'country_year_sector_gas_values')
            ->assertJsonPath('meta.applied_filters.sector_code', 'agriculture')
            ->assertJsonPath('meta.applied_filters.gas_code', 'kyotoghg')
            ->assertJsonPath('meta.total_matching_rows', 48)
            ->assertJsonPath('meta.truncated', false)
            ->assertJsonCount(48, 'data')
            ->assertJsonFragment([
                'country_code' => 'NGA',
                'sector_code' => 'agriculture',
                'gas_code' => 'kyotoghg',
                'source_code' => 'pik',
            ]);

        $nigeria = collect($response->json('data'))->firstWhere('country_code', 'NGA');

        $this->assertNotNull($nigeria);
        $this->assertSame('CW_HistoricalEmissions_PRIMAP.csv', $nigeria['metadata']['source_file']);
        $this->assertSame('PIK', $nigeria['metadata']['source_series']);
    }

    public function test_it_exports_filtered_dataset_records_as_csv(): void
    {
        $dataset = Dataset::query()->where('code', 'nigeria_climate_policy_documents')->firstOrFail();

        $response = $this->get('/api/datasets/nigeria_climate_policy_documents/export.csv?status=published&document_type_code=ndc');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('x-dataset-code', 'nigeria_climate_policy_documents')
            ->assertHeader('x-dataset-version', 'sample-v1')
            ->assertHeader('x-export-total-matching-rows', '1')
            ->assertHeader('x-export-truncated', 'false');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('country_code,document_code,document_type_code,document_type_name,title,submission_date,status,summary,payload', $csv);
        $this->assertStringContainsString('NGA,nga-ndc-2021,ndc', $csv);
        $this->assertStringNotContainsString('nga-nap-draft', $csv);
        $this->assertSame(1, $dataset->fresh()->download_count);
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\AdminPeriodIndicatorValue;
use App\Models\Dataset;
use App\Models\DatasetVersion;
use App\Models\Geography;
use App\Models\GeographyShape;
use App\Models\Indicator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MapApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_geometry_feed_returns_only_published_database_backed_shapes(): void
    {
        $published = Geography::query()->where('code', 'NG002')->firstOrFail();
        $draft = Geography::query()->where('code', 'NG025')->firstOrFail();

        $this->shape($published, 'published');
        $this->shape($draft, 'draft');

        $response = $this->getJson('/api/maps/geographies/1/geometry?boundary_version=v01');

        $response
            ->assertOk()
            ->assertHeader('x-cache-ttl', '3600')
            ->assertHeader('x-payload-max-bytes', (string) (1024 * 1024))
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonPath('meta.geometry_status', 'published')
            ->assertJsonPath('meta.feature_count', 1)
            ->assertJsonPath('features.0.properties.geography_code', 'NG002');

        $this->assertStringContainsString('max-age=3600', $response->headers->get('cache-control'));
        $this->assertStringNotContainsString('source_path', $response->getContent());
        $this->assertStringNotContainsString('climate-data/boundaries', $response->getContent());
    }

    public function test_geometry_feed_omits_empty_centroid_metadata(): void
    {
        $published = Geography::query()->where('code', 'NG002')->firstOrFail();

        $this->shape($published, 'published');

        $response = $this->getJson('/api/maps/geographies/1/geometry?boundary_version=v01');

        $response
            ->assertOk()
            ->assertJsonMissingPath('features.0.properties.centroid');
    }

    public function test_geometry_feed_serves_a_published_simplification_variant(): void
    {
        $published = Geography::query()->where('code', 'NG002')->firstOrFail();

        $this->shape($published, 'published', null, 'public-1mb');

        $this->getJson('/api/maps/geographies/1/geometry?boundary_version=v01&simplification=public-1mb')
            ->assertOk()
            ->assertJsonPath('meta.geometry_status', 'published')
            ->assertJsonPath('meta.simplification', 'public-1mb')
            ->assertJsonPath('features.0.properties.geography_code', 'NG002');
    }

    public function test_geometry_feed_rejects_payloads_over_the_ops_ceiling(): void
    {
        $published = Geography::query()->where('code', 'NG002')->firstOrFail();
        $coordinates = [[7.0, 5.0]];

        for ($index = 0; $index < 75000; $index++) {
            $coordinates[] = [7.0 + ($index / 1000000), 5.0 + ($index / 1000000)];
        }

        $coordinates[] = [7.0, 5.0];

        $this->shape($published, 'published', json_encode([
            'type' => 'Polygon',
            'coordinates' => [$coordinates],
        ], JSON_THROW_ON_ERROR));

        $this->getJson('/api/maps/geographies/1/geometry?boundary_version=v01')
            ->assertStatus(413);
    }

    public function test_map_endpoints_reject_array_query_parameters(): void
    {
        $this->getJson('/api/maps/geographies/1/geometry?parent_code[]=NG001')
            ->assertUnprocessable();
    }

    public function test_public_map_endpoints_reject_draft_geometry_status(): void
    {
        $this->getJson('/api/maps/geographies/1/geometry?geometry_status=draft')
            ->assertUnprocessable();

        $this->getJson('/api/maps/datasets/nigeria_rainfall_subnational/choropleth?geography_level=1&indicator_code=rfh&geometry_status=draft')
            ->assertUnprocessable();
    }

    public function test_values_feed_returns_rainfall_values_without_geometry(): void
    {
        $this->publishedAdmin1RainfallFixture();

        $response = $this->getJson('/api/maps/datasets/nigeria_rainfall_subnational/values?geography_level=1&indicator_code=rfh&period_type=dekad');

        $response
            ->assertOk()
            ->assertHeader('x-cache-ttl', '300')
            ->assertJsonPath('meta.dataset_code', 'nigeria_rainfall_subnational')
            ->assertJsonPath('meta.indicator_code', 'rfh')
            ->assertJsonPath('meta.value_count', 2)
            ->assertJsonFragment(['geography_code' => 'NG002'])
            ->assertJsonFragment(['geography_code' => 'NG025']);

        $this->assertStringNotContainsString('geometry_geojson', $response->getContent());
        $this->assertStringNotContainsString('"geometry"', $response->getContent());
    }

    public function test_choropleth_can_include_a_paired_long_term_rainfall_comparison(): void
    {
        $this->publishedAdmin1RainfallFixture();
        $dataset = Dataset::query()->where('code', 'nigeria_rainfall_subnational')->firstOrFail();
        $version = $dataset->versions()->where('version_label', 'sample-v1')->firstOrFail();
        $average = Indicator::query()->where('code', 'rfh_avg')->firstOrFail();

        foreach (['NG002' => 10.0, 'NG025' => 15.0] as $code => $value) {
            $this->rainfallValue($dataset, $version, $average, Geography::query()->where('code', $code)->firstOrFail(), $value);
        }

        $this->getJson('/api/maps/datasets/nigeria_rainfall_subnational/choropleth?geography_level=1&indicator_code=rfh&comparison_indicator_code=rfh_avg&period_type=dekad&include_geometry=false')
            ->assertOk()
            ->assertJsonFragment(['geography_code' => 'NG002', 'comparison_value' => 10.0, 'comparison_unit' => 'mm'])
            ->assertJsonFragment(['geography_code' => 'NG025', 'comparison_value' => 15.0, 'comparison_unit' => 'mm']);
    }

    public function test_risk_indicator_dataset_uses_map_contract_with_indicator_filtering_and_no_value_states(): void
    {
        $this->publishedAdmin2RiskFixture();

        $values = $this->getJson('/api/maps/datasets/nigeria_risk_assessment_indicators/values?geography_level=2&indicator_code=flood_exposure_score&period_type=baseline&parent_code=NG001');

        $values
            ->assertOk()
            ->assertHeader('x-cache-ttl', '300')
            ->assertHeader('x-payload-max-bytes', (string) (512 * 1024))
            ->assertJsonPath('meta.dataset_code', 'nigeria_risk_assessment_indicators')
            ->assertJsonPath('meta.indicator_code', 'flood_exposure_score')
            ->assertJsonPath('meta.value_count', 2)
            ->assertJsonMissing(['indicator_code' => 'risk_vulnerability_score']);

        $choropleth = $this->getJson('/api/maps/datasets/nigeria_risk_assessment_indicators/choropleth?geography_level=2&indicator_code=flood_exposure_score&period_type=baseline&parent_code=NG001&include_geometry=false&simplification=public-1mb');

        $choropleth
            ->assertOk()
            ->assertHeader('x-cache-ttl', '300')
            ->assertHeader('x-payload-max-bytes', (string) (768 * 1024))
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonPath('meta.dataset_code', 'nigeria_risk_assessment_indicators')
            ->assertJsonPath('meta.indicator_code', 'flood_exposure_score')
            ->assertJsonPath('meta.include_geometry', false)
            ->assertJsonPath('quality.geometry_count', 3)
            ->assertJsonPath('quality.value_count', 2)
            ->assertJsonPath('quality.geometry_without_value_count', 1)
            ->assertJsonPath('quality.geometry_without_value_pcodes.0', 'NG001003')
            ->assertJsonPath('quality.unmatched_fact_pcode_count', 0)
            ->assertJsonPath('features.0.geometry', null)
            ->assertJsonFragment(['value_status' => 'no_value'])
            ->assertJsonFragment(['value_status' => 'value']);

        $this->assertStringNotContainsString('geometry_geojson', $choropleth->getContent());
        $this->assertStringNotContainsString('source_path', $choropleth->getContent());
        $this->assertStringNotContainsString('climate-data/boundaries', $choropleth->getContent());
    }

    public function test_choropleth_preserves_geometry_without_value_states(): void
    {
        $this->publishedAdmin2RainfallFixture();

        $response = $this->getJson('/api/maps/datasets/nigeria_rainfall_subnational/choropleth?geography_level=2&indicator_code=rfh&period_type=dekad&parent_code=NG001');

        $response
            ->assertOk()
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonPath('quality.geometry_count', 3)
            ->assertJsonPath('quality.value_count', 2)
            ->assertJsonPath('quality.unmatched_fact_pcode_count', 0)
            ->assertJsonPath('quality.geometry_without_value_count', 1)
            ->assertJsonPath('quality.geometry_without_value_pcodes.0', 'NG001003')
            ->assertJsonFragment(['value_status' => 'no_value'])
            ->assertJsonFragment(['value_status' => 'value']);
    }

    public function test_quality_feed_reports_no_value_boundaries(): void
    {
        $this->publishedAdmin2RainfallFixture();

        $response = $this->getJson('/api/maps/datasets/nigeria_rainfall_subnational/quality?geography_level=2&indicator_code=rfh&period_type=dekad&parent_code=NG001');

        $response
            ->assertOk()
            ->assertJsonPath('data.geometry_count', 3)
            ->assertJsonPath('data.value_count', 2)
            ->assertJsonPath('data.geometry_without_value_count', 1)
            ->assertJsonPath('data.geometry_without_value_pcodes.0', 'NG001003')
            ->assertJsonPath('data.unmatched_fact_pcode_count', 0);
    }

    public function test_quality_feed_reports_unmatched_values_when_no_geometry_is_published(): void
    {
        $response = $this->getJson('/api/maps/datasets/nigeria_rainfall_subnational/quality?geography_level=1&indicator_code=rfh&period_type=dekad');

        $response
            ->assertOk()
            ->assertJsonPath('data.geometry_count', 0)
            ->assertJsonPath('data.value_count', 5)
            ->assertJsonPath('data.unmatched_fact_pcode_count', 5)
            ->assertJsonPath('data.geometry_without_value_count', 0);
    }

    public function test_children_feed_supports_admin2_drilldown_without_geometry_by_default(): void
    {
        $this->publishedAdmin2RainfallFixture();

        $response = $this->getJson('/api/maps/geographies/NG001/children?boundary_version=v01');

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.parent_code', 'NG001')
            ->assertJsonPath('data.0.parent_code', 'NG001')
            ->assertJsonPath('data.0.shape_available', true)
            ->assertJsonPath('data.0.geometry', null);
    }

    public function test_map_values_are_limited_to_admin_period_indicator_datasets(): void
    {
        $this->getJson('/api/maps/datasets/climatewatch_historical_emissions/values?geography_level=1&indicator_code=rfh')
            ->assertUnprocessable();
    }

    public function test_map_values_require_indicator_code(): void
    {
        $this->getJson('/api/maps/datasets/nigeria_rainfall_subnational/values?geography_level=1')
            ->assertUnprocessable();
    }

    private function publishedAdmin1RainfallFixture(): void
    {
        $dataset = Dataset::query()->where('code', 'nigeria_rainfall_subnational')->firstOrFail();
        $version = $dataset->versions()->where('version_label', 'sample-v1')->firstOrFail();
        $indicator = Indicator::query()->where('code', 'rfh')->firstOrFail();

        AdminPeriodIndicatorValue::query()
            ->where('dataset_version_id', $version->id)
            ->delete();

        foreach (['NG002' => 9.5, 'NG025' => 12.25] as $code => $value) {
            $geography = Geography::query()->where('code', $code)->firstOrFail();
            $this->shape($geography, 'published');
            $this->rainfallValue($dataset, $version, $indicator, $geography, $value);
        }
    }

    private function publishedAdmin2RainfallFixture(): void
    {
        $dataset = Dataset::query()->where('code', 'nigeria_rainfall_subnational')->firstOrFail();
        $version = $dataset->versions()->where('version_label', 'sample-v1')->firstOrFail();
        $indicator = Indicator::query()->where('code', 'rfh')->firstOrFail();
        $parent = Geography::query()->updateOrCreate(
            ['code' => 'NG001'],
            [
                'source_pcode' => 'NG001',
                'name' => 'Abia',
                'country_code' => 'NGA',
                'level' => 1,
                'type' => 'administrative',
                'boundary_version' => 'v01',
            ]
        );

        AdminPeriodIndicatorValue::query()
            ->where('dataset_version_id', $version->id)
            ->delete();

        foreach (['NG001001', 'NG001002', 'NG001003'] as $index => $code) {
            $geography = Geography::query()->updateOrCreate(
                ['code' => $code],
                [
                    'source_pcode' => $code,
                    'name' => "LGA {$index}",
                    'country_code' => 'NGA',
                    'level' => 2,
                    'parent_id' => $parent->id,
                    'type' => 'administrative',
                    'boundary_version' => 'v01',
                ]
            );
            $this->shape($geography, 'published');

            if ($code !== 'NG001003') {
                $this->rainfallValue($dataset, $version, $indicator, $geography, 10 + $index);
            }
        }
    }

    private function publishedAdmin2RiskFixture(): void
    {
        $dataset = Dataset::query()->where('code', 'nigeria_risk_assessment_indicators')->firstOrFail();
        $version = $dataset->versions()->where('version_label', 'sample-v1')->firstOrFail();
        $floodExposure = Indicator::query()->where('code', 'flood_exposure_score')->firstOrFail();
        $vulnerability = Indicator::query()->where('code', 'risk_vulnerability_score')->firstOrFail();
        $parent = Geography::query()->updateOrCreate(
            ['code' => 'NG001'],
            [
                'source_pcode' => 'NG001',
                'name' => 'Abia',
                'country_code' => 'NGA',
                'level' => 1,
                'type' => 'administrative',
                'boundary_version' => 'v01',
            ]
        );

        AdminPeriodIndicatorValue::query()
            ->where('dataset_version_id', $version->id)
            ->delete();

        foreach (['NG001001', 'NG001002', 'NG001003'] as $index => $code) {
            $geography = Geography::query()->updateOrCreate(
                ['code' => $code],
                [
                    'source_pcode' => $code,
                    'name' => "LGA {$index}",
                    'country_code' => 'NGA',
                    'level' => 2,
                    'parent_id' => $parent->id,
                    'type' => 'administrative',
                    'boundary_version' => 'v01',
                ]
            );
            $this->shape($geography, 'published', null, 'public-1mb');

            if ($code !== 'NG001003') {
                $this->adminPeriodIndicatorValue($dataset, $version, $floodExposure, $geography, 0.4 + ($index / 10), 'baseline');
                $this->adminPeriodIndicatorValue($dataset, $version, $vulnerability, $geography, 0.7 + ($index / 10), 'baseline');
            }
        }
    }

    private function shape(Geography $geography, string $status, ?string $geometryGeojson = null, string $simplification = 'overview'): GeographyShape
    {
        return GeographyShape::query()->updateOrCreate(
            [
                'geography_id' => $geography->id,
                'boundary_version' => 'v01',
                'simplification' => $simplification,
            ],
            [
                'geography_level' => $geography->level,
                'geometry_status' => $status,
                'source_path' => 'database-backed-test-fixture',
                'source_layer' => 'test',
                'source_feature_id' => $geography->code,
                'geometry_geojson' => $geometryGeojson ?? json_encode([
                    'type' => 'Polygon',
                    'coordinates' => [[
                        [7.0, 5.0],
                        [7.1, 5.0],
                        [7.1, 5.1],
                        [7.0, 5.1],
                        [7.0, 5.0],
                    ]],
                ], JSON_THROW_ON_ERROR),
                'bbox' => [7.0, 5.0, 7.1, 5.1],
                'metadata' => ['test' => true],
            ]
        );
    }

    private function rainfallValue(Dataset $dataset, DatasetVersion $version, Indicator $indicator, Geography $geography, float $value): void
    {
        $this->adminPeriodIndicatorValue($dataset, $version, $indicator, $geography, $value, 'dekad');
    }

    private function adminPeriodIndicatorValue(Dataset $dataset, DatasetVersion $version, Indicator $indicator, Geography $geography, float $value, string $periodType): void
    {
        AdminPeriodIndicatorValue::query()->updateOrCreate(
            [
                'dataset_version_id' => $version->id,
                'indicator_id' => $indicator->id,
                'geography_code' => $geography->code,
                'period_date' => '2026-04-11',
            ],
            [
                'source_id' => $dataset->source_id,
                'geography_id' => $geography->id,
                'period_type' => $periodType,
                'value' => $value,
                'metadata' => ['test' => true],
            ]
        );
    }
}

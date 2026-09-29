<?php

namespace Tests\Feature\Imports;

use App\Models\Geography;
use App\Models\GeographyShape;
use App\Services\Geographies\BoundaryGeoJsonImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoundaryGeoJsonImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_reports_features_without_persisting_boundary_rows(): void
    {
        $path = base_path('tests/Fixtures/boundaries/admin1-sample.geojson');

        $summary = app(BoundaryGeoJsonImporter::class)->preview($path, 1, [
            'source_name' => 'test-boundary',
        ]);

        $this->assertSame('v01', $summary['boundary_version']);
        $this->assertSame(1, $summary['level']);
        $this->assertSame(1, $summary['scanned_features']);
        $this->assertSame(1, $summary['imported_geographies']);
        $this->assertSame(1, $summary['imported_shapes']);
        $this->assertSame([], $summary['errors']);
        $this->assertDatabaseMissing('geography_shapes', [
            'boundary_version' => 'v01',
            'source_feature_id' => 'NG001',
        ]);
    }

    public function test_import_persists_admin1_boundary_shape_and_aliases(): void
    {
        $path = base_path('tests/Fixtures/boundaries/admin1-sample.geojson');

        $summary = app(BoundaryGeoJsonImporter::class)->import($path, 1, [
            'source_name' => 'test-boundary',
            'geometry_status' => 'published',
        ]);

        $this->assertSame(1, $summary['imported_geographies']);
        $this->assertSame(1, $summary['imported_shapes']);

        $geography = Geography::query()
            ->where('code', 'NG001')
            ->with(['parent', 'aliases', 'shapes'])
            ->firstOrFail();

        $this->assertSame('Abia', $geography->name);
        $this->assertSame('NG', $geography->parent?->code);
        $this->assertSame('v01', $geography->boundary_version);
        $this->assertSame(1, Geography::query()->where('level', 0)->count());
        $this->assertTrue($geography->aliases->contains('normalized_alias', 'abia'));

        $shape = $geography->shapes->firstWhere('boundary_version', 'v01');
        $this->assertNotNull($shape);
        $this->assertSame('published', $shape->geometry_status);
        $this->assertSame('overview', $shape->simplification);
        $this->assertEquals([7.0, 5.0, 7.1, 5.1], $shape->bbox);
        $this->assertNotNull($shape->geometry_geojson);
    }

    public function test_import_persists_admin2_parent_and_senatorial_alias_metadata(): void
    {
        $path = base_path('tests/Fixtures/boundaries/admin2-sample.geojson');

        app(BoundaryGeoJsonImporter::class)->import($path, 2, [
            'source_name' => 'test-boundary',
        ]);

        $geography = Geography::query()
            ->where('code', 'NG001001')
            ->with('parent')
            ->firstOrFail();

        $this->assertSame('Aba North', $geography->name);
        $this->assertSame('NG001', $geography->parent?->code);
        $this->assertSame('NG00103', $geography->alternate_codes['sendistpcode']);

        $this->assertDatabaseHas('geography_shapes', [
            'geography_id' => $geography->id,
            'boundary_version' => 'v01',
            'geography_level' => 2,
            'source_feature_id' => 'NG001001',
        ]);
    }

    public function test_import_merges_existing_ng_and_legacy_nga_country_records(): void
    {
        Geography::query()->create([
            'code' => 'NG',
            'source_pcode' => 'NG',
            'name' => 'Nigeria',
            'country_code' => 'NGA',
            'level' => 0,
            'type' => 'country',
        ]);

        app(BoundaryGeoJsonImporter::class)->import(
            base_path('tests/Fixtures/boundaries/admin1-sample.geojson'),
            1
        );

        $this->assertSame(1, Geography::query()->where('level', 0)->count());
        $this->assertDatabaseHas('geographies', [
            'code' => 'NG',
            'source_pcode' => 'NG',
            'level' => 0,
        ]);
        $this->assertDatabaseMissing('geographies', [
            'code' => 'NGA',
            'level' => 0,
        ]);
    }

    public function test_console_command_previews_admin1_boundary_import(): void
    {
        $this->artisan('climate:import-boundary-geojson', [
            'file' => base_path('tests/Fixtures/boundaries/admin1-sample.geojson'),
            'level' => 1,
            '--source-name' => 'test-boundary',
            '--preview' => true,
        ])
            ->expectsOutput('Previewed boundary GeoJSON level [1] version [v01].')
            ->expectsOutput('Scanned features: 1')
            ->expectsOutput('Imported geographies: 1')
            ->expectsOutput('Imported shapes: 1')
            ->expectsOutput('Errors: 0')
            ->assertExitCode(0);

        $this->assertSame(0, GeographyShape::query()
            ->where('boundary_version', 'v01')
            ->where('source_feature_id', 'NG001')
            ->count());
    }
}

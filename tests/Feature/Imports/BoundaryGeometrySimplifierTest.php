<?php

namespace Tests\Feature\Imports;

use App\Models\Geography;
use App\Models\GeographyShape;
use App\Services\Geographies\BoundaryGeometrySimplifier;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoundaryGeometrySimplifierTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    public function test_preview_reports_simplified_shapes_without_persisting_target_rows(): void
    {
        $this->sourceShape('NG002');

        $summary = app(BoundaryGeometrySimplifier::class)->preview([
            'levels' => [1],
            'target_simplification' => 'public-1mb',
            'tolerance' => 0.0002,
            'precision' => 6,
        ]);

        $this->assertSame('v01', $summary['boundary_version']);
        $this->assertSame('overview', $summary['source_simplification']);
        $this->assertSame('public-1mb', $summary['target_simplification']);
        $this->assertSame('draft', $summary['target_status']);
        $this->assertSame(1, $summary['levels'][1]['source_count']);
        $this->assertSame(1, $summary['levels'][1]['target_count']);
        $this->assertLessThan($summary['levels'][1]['source_points'], $summary['levels'][1]['target_points']);

        $this->assertDatabaseMissing('geography_shapes', [
            'simplification' => 'public-1mb',
        ]);
    }

    public function test_simplify_persists_derived_shape_with_source_metadata(): void
    {
        $sourceShape = $this->sourceShape('NG002');

        app(BoundaryGeometrySimplifier::class)->simplify([
            'levels' => [1],
            'target_simplification' => 'public-1mb',
            'target_status' => 'published',
            'tolerance' => 0.0002,
            'precision' => 6,
        ]);

        $geography = Geography::query()->where('code', 'NG002')->firstOrFail();
        $shape = GeographyShape::query()
            ->where('geography_id', $geography->id)
            ->where('boundary_version', 'v01')
            ->where('simplification', 'public-1mb')
            ->firstOrFail();

        $this->assertSame('published', $shape->geometry_status);
        $this->assertSame('overview', $shape->metadata['derived_from_simplification']);
        $this->assertSame('published', $shape->metadata['derived_from_status']);
        $this->assertSame(0.0002, $shape->metadata['simplification_tolerance']);
        $this->assertSame(6, $shape->metadata['coordinate_precision']);
        $this->assertLessThan(strlen($sourceShape->geometry_geojson), strlen($shape->geometry_geojson));
    }

    public function test_console_command_previews_public_boundary_simplification(): void
    {
        $this->sourceShape('NG002');

        $this->artisan('climate:simplify-boundary-geometry', [
            '--levels' => '1',
            '--target-simplification' => 'public-1mb',
            '--preview' => true,
        ])
            ->expectsOutput('Previewed boundary simplification [public-1mb] from [overview].')
            ->expectsOutput('Boundary version: v01')
            ->expectsOutput('Source status: published')
            ->expectsOutput('Target status: draft')
            ->expectsOutput('Level 1: 1 source shapes, 1 target shapes')
            ->assertExitCode(0);
    }

    private function sourceShape(string $code): GeographyShape
    {
        $geography = Geography::query()->where('code', $code)->firstOrFail();
        $coordinates = [[7.0, 5.0]];

        for ($index = 1; $index < 50; $index++) {
            $coordinates[] = [
                7.0 + ($index * 0.001),
                5.0 + (($index % 2) * 0.00001),
            ];
        }

        $coordinates[] = [7.1, 5.1];
        $coordinates[] = [7.0, 5.0];

        return GeographyShape::query()->updateOrCreate([
            'geography_id' => $geography->id,
            'boundary_version' => 'v01',
            'simplification' => 'overview',
        ], [
            'geography_id' => $geography->id,
            'boundary_version' => 'v01',
            'geography_level' => 1,
            'geometry_status' => 'published',
            'simplification' => 'overview',
            'source_path' => 'database-backed-test-fixture',
            'source_layer' => 'test',
            'source_feature_id' => $geography->code,
            'geometry_geojson' => json_encode([
                'type' => 'Polygon',
                'coordinates' => [$coordinates],
            ], JSON_THROW_ON_ERROR),
            'bbox' => [7.0, 5.0, 7.1, 5.1],
            'metadata' => ['test' => true],
        ]);
    }
}

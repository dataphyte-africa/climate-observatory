<?php

namespace Tests\Feature\Schema;

use App\Models\Geography;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClimateSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    public function test_geography_tables_include_pcode_shape_and_alias_contracts(): void
    {
        $this->assertTrue(Schema::hasColumns('geographies', [
            'code',
            'source_pcode',
            'boundary_version',
            'valid_from',
            'valid_to',
            'alternate_codes',
        ]));

        $this->assertTrue(Schema::hasColumns('geography_aliases', [
            'geography_id',
            'source_name',
            'alias_type',
            'alias_value',
            'normalized_alias',
            'metadata',
        ]));

        $this->assertTrue(Schema::hasColumns('geography_shapes', [
            'geography_id',
            'boundary_version',
            'geography_level',
            'geometry_status',
            'simplification',
            'source_path',
            'source_layer',
            'source_feature_id',
            'geometry_geojson',
            'centroid_latitude',
            'centroid_longitude',
            'bbox',
            'area_sq_km',
            'metadata',
        ]));
    }

    public function test_seeded_geographies_have_database_backed_shape_and_alias_records(): void
    {
        $geography = Geography::query()
            ->where('code', 'NG002')
            ->with(['aliases', 'shapes'])
            ->firstOrFail();

        $this->assertSame('NG002', $geography->source_pcode);
        $this->assertSame('sample-boundary-v1', $geography->boundary_version);
        $this->assertTrue($geography->aliases->contains('normalized_alias', 'ng002'));
        $this->assertTrue($geography->shapes->contains(function ($shape) {
            return $shape->boundary_version === 'sample-boundary-v1'
                && $shape->geometry_status === 'sample'
                && $shape->simplification === 'overview';
        }));
    }

    public function test_default_statamic_homepage_flat_file_is_not_tracked_as_content(): void
    {
        $this->assertFileDoesNotExist(base_path('content/collections/pages/home.md'));
        $this->assertFileDoesNotExist(base_path('content/trees/collections/pages.yaml'));
    }

    public function test_retired_data_files_collection_is_not_registered(): void
    {
        $this->assertFileDoesNotExist(base_path('content/collections/data_files.yaml'));
        $this->assertFileDoesNotExist(base_path('resources/blueprints/collections/data_files/data_file.yaml'));
        $this->assertDatabaseMissing('collections', ['handle' => 'data_files']);
        $this->assertSame(0, DB::table('entries')->where('collection', 'data_files')->count());
    }

    public function test_statamic_assets_container_definition_uses_the_public_assets_disk(): void
    {
        $this->assertSame(public_path('assets'), config('filesystems.disks.assets.root'));
        $this->assertSame('/assets', config('filesystems.disks.assets.url'));
        $this->assertStringContainsString('disk: assets', file_get_contents(base_path('content/assets/assets.yaml')));
    }
}

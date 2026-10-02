<?php

namespace Tests\Feature\Imports;

use App\Imports\CsvDatasetImporter;
use App\Imports\Exceptions\CsvImportException;
use App\Jobs\ProcessCsvDatasetImport;
use App\Models\Dataset;
use App\Models\DatasetImport;
use App\Models\DatasetVersion;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CsvDatasetImporterTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    public function test_preview_validates_and_transforms_without_persisting_fact_rows(): void
    {
        $version = $this->newVersion('climatewatch_historical_emissions', 'preview-fixture');
        $path = base_path('tests/Fixtures/imports/wide-country-sector-gas-year-valid.csv');
        $existingRows = DB::table('country_year_sector_gas_values')->count();

        $preview = app(CsvDatasetImporter::class)->preview(
            'wide_country_sector_gas_year',
            $path,
            $version
        );

        $this->assertSame('wide_country_sector_gas_year', $preview['schema_key']);
        $this->assertSame('country_year_sector_gas_values', $preview['target_table']);
        $this->assertSame(['country', 'sector', 'gas', 'source', '1990', '1991'], $preview['headers']);
        $this->assertSame(2, $preview['scanned_rows']);
        $this->assertSame(4, $preview['accepted_rows']);
        $this->assertSame(0, $preview['rejected_rows']);
        $this->assertCount(4, $preview['sample_records']);
        $this->assertSame($existingRows, DB::table('country_year_sector_gas_values')->count());
    }

    public function test_import_stages_validation_errors_without_persisting_fact_rows(): void
    {
        $version = $this->newVersion('climatewatch_historical_emissions', 'invalid-row-fixture');
        $path = base_path('tests/Fixtures/imports/wide-country-sector-gas-year-invalid-row.csv');

        $import = app(CsvDatasetImporter::class)->import(
            'wide_country_sector_gas_year',
            $path,
            $version
        );

        $this->assertSame('validation_failed', $import->status);
        $this->assertSame(2, $import->scanned_rows);
        $this->assertSame(2, $import->accepted_rows);
        $this->assertSame(1, $import->rejected_rows);
        $this->assertSame('validated_with_errors', $version->fresh()->status);
        $this->assertDatabaseHas('import_errors', [
            'import_id' => $import->id,
            'row_number' => 3,
            'column_name' => '1990',
            'error_code' => 'row_validation_failed',
            'message' => 'Year value must be numeric.',
        ]);
        $this->assertSame(0, DB::table('country_year_sector_gas_values')->where('dataset_version_id', $version->id)->count());
        $this->assertSame(2, DB::table('import_staged_records')->where('import_id', $import->id)->count());
    }

    public function test_pik_emissions_source_is_labelled_as_primap(): void
    {
        $version = $this->newVersion('climatewatch_historical_emissions', 'primap-source-fixture');

        app(CsvDatasetImporter::class)->import(
            'wide_country_sector_gas_year',
            base_path('tests/Fixtures/imports/wide-country-sector-gas-year-primap.csv'),
            $version
        );

        $this->assertDatabaseHas('sources', [
            'code' => 'pik',
            'name' => 'PRIMAP',
        ]);
    }

    public function test_emissions_reimport_rejects_existing_natural_keys_without_upserting_them(): void
    {
        $version = $this->newVersion('climatewatch_historical_emissions', 'incremental-fixture');
        $importer = app(CsvDatasetImporter::class);
        $operator = User::factory()->create();

        $first = $importer->import(
            'wide_country_sector_gas_year',
            base_path('tests/Fixtures/imports/wide-country-sector-gas-year-valid.csv'),
            $version
        );

        $this->assertSame('awaiting_approval', $first->status);
        $this->assertSame(0, DB::table('country_year_sector_gas_values')->where('dataset_version_id', $version->id)->count());

        $importer->apply($importer->approve($first, $operator->id), $operator->id);

        $this->assertSame(4, DB::table('country_year_sector_gas_values')->where('dataset_version_id', $version->id)->count());

        $second = $importer->import(
            'wide_country_sector_gas_year',
            base_path('tests/Fixtures/imports/wide-country-sector-gas-year-incremental.csv'),
            $version
        );

        $this->assertSame('validation_failed', $second->fresh()->status);
        $this->assertSame(1, $second->rejected_rows);
        $this->assertSame(4, DB::table('country_year_sector_gas_values')->where('dataset_version_id', $version->id)->count());
        $this->assertSame(12.5, (float) DB::table('country_year_sector_gas_values')
            ->where('dataset_version_id', $version->id)
            ->where('country_code', 'NGA')
            ->where('year', 1990)
            ->value('value'));
    }

    #[DataProvider('controlledSchemaSamples')]
    public function test_every_controlled_fact_schema_stages_and_applies_a_sample(string $datasetCode, string $schema, string $fixture, string $table): void
    {
        $version = $this->newVersion($datasetCode, "{$schema}-fixture");
        $operator = User::factory()->create();
        $importer = app(CsvDatasetImporter::class);

        $import = $importer->import($schema, base_path("tests/Fixtures/imports/{$fixture}"), $version);

        $this->assertSame('awaiting_approval', $import->status);
        $this->assertSame(0, DB::table($table)->where('dataset_version_id', $version->id)->count());
        $importer->apply($importer->approve($import, $operator->id), $operator->id);
        $this->assertSame(1, DB::table($table)->where('dataset_version_id', $version->id)->count());
    }

    /** @return array<string, array<int, string>> */
    public static function controlledSchemaSamples(): array
    {
        return [
            'country-year indicator' => ['nigeria_country_year_indicators', 'country_year_indicator', 'country-year-indicator-valid.csv', 'country_year_indicator_values'],
            'country document' => ['nigeria_climate_policy_documents', 'country_document', 'country-document-valid.csv', 'country_documents'],
            'document sector response' => ['nigeria_document_sector_responses', 'country_document_sector_response', 'country-document-sector-response-valid.csv', 'country_document_sector_responses'],
            'event impact' => ['nigeria_flood_event_impacts', 'event_impact', 'event-impact-valid.csv', 'event_impacts'],
        ];
    }

    public function test_import_rejects_dataset_schema_mismatches_with_failed_import_log(): void
    {
        $version = $this->newVersion('nigeria_rainfall_subnational', 'schema-mismatch-fixture');
        $path = base_path('tests/Fixtures/imports/wide-country-sector-gas-year-valid.csv');

        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('expects import schema [long_admin_period_indicator]');

        try {
            app(CsvDatasetImporter::class)->import(
                'wide_country_sector_gas_year',
                $path,
                $version
            );
        } finally {
            $this->assertDatabaseHas('imports', [
                'dataset_version_id' => $version->id,
                'schema_key' => 'wide_country_sector_gas_year',
                'status' => 'failed',
                'error_count' => 1,
            ]);
            $this->assertSame('import_failed', $version->fresh()->status);
        }
    }

    public function test_import_rejects_duplicate_normalized_headers(): void
    {
        $version = $this->newVersion('climatewatch_historical_emissions', 'duplicate-header-fixture');
        $path = base_path('tests/Fixtures/imports/wide-country-sector-gas-year-duplicate-header.csv');

        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('duplicate column [1990]');

        app(CsvDatasetImporter::class)->preview(
            'wide_country_sector_gas_year',
            $path,
            $version
        );
    }

    public function test_console_queue_creates_pending_import_log_and_dispatches_job(): void
    {
        Queue::fake();

        $path = base_path('tests/Fixtures/imports/wide-country-sector-gas-year-valid.csv');

        $this->artisan('climate:import-csv', [
            'dataset_code' => 'climatewatch_historical_emissions',
            'schema' => 'wide_country_sector_gas_year',
            'file' => $path,
            '--version-label' => 'queued-fixture',
            '--queue' => true,
        ])->assertExitCode(0);

        $import = DatasetImport::query()->latest('id')->firstOrFail();
        $version = DatasetVersion::query()
            ->where('version_label', 'queued-fixture')
            ->firstOrFail();

        $this->assertSame($version->id, $import->dataset_version_id);
        $this->assertSame('pending', $import->status);
        $this->assertNull($import->source_hash);
        $this->assertSame('queued', $version->status);

        Queue::assertPushed(ProcessCsvDatasetImport::class, function (ProcessCsvDatasetImport $job) use ($import, $version, $path): bool {
            return $job->importId === $import->id
                && $job->datasetVersionId === $version->id
                && $job->schemaKey === 'wide_country_sector_gas_year'
                && $job->path === $path;
        });
    }

    private function newVersion(string $datasetCode, string $label): DatasetVersion
    {
        $dataset = Dataset::query()->where('code', $datasetCode)->firstOrFail();

        return DatasetVersion::create([
            'dataset_id' => $dataset->id,
            'version_label' => $label,
            'status' => 'draft',
        ]);
    }
}

<?php

namespace Tests\Feature\Cp;

use App\Models\AdminPeriodIndicatorValue;
use App\Models\AdminPeriodIndicatorValueEdit;
use App\Models\Dataset;
use App\Models\DatasetImport;
use App\Models\DatasetVersion;
use App\Models\Geography;
use App\Models\Indicator;
use App\Models\Source;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Statamic\Facades\Blink;
use Tests\TestCase;

class RainfallCpControllerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    public function test_rainfall_workspace_uses_the_statamic_cp_shell_without_a_collection_projection(): void
    {
        $this->seedRainfallRecord();

        $this->actingAs($this->operator())
            ->get(route('statamic.cp.climatehub.rainfall.index'))
            ->assertOk()
            ->assertSee('Rainfall data')
            ->assertSee('World Food Programme')
            ->assertSee('Rainfall records')
            ->assertDontSee('native entries')
            ->assertDontSee('Sync Rainfall Facts')
            ->assertDontSee('Dataset Registry')
            ->assertDontSee('Import Review')
            ->assertDontSee('Quality Review');
    }

    public function test_legacy_operations_routes_are_removed(): void
    {
        $this->actingAs($this->operator())
            ->get('/cp/climatehub/rainfall-records')
            ->assertNotFound();
    }

    public function test_rainfall_records_support_sorting_and_an_inclusive_period_range(): void
    {
        $record = $this->seedRainfallRecord();
        $geography = Geography::query()->updateOrCreate(
            ['code' => 'NGA-TEST-02'],
            ['name' => 'Second Test State', 'level' => 1, 'source_pcode' => 'NGA-TEST-02'],
        );
        $lowerValueRecord = AdminPeriodIndicatorValue::query()->create([
            'dataset_version_id' => $record->dataset_version_id,
            'source_id' => $record->source_id,
            'geography_id' => $geography->id,
            'indicator_id' => $record->indicator_id,
            'geography_code' => $geography->code,
            'period_date' => '2026-09-01',
            'period_type' => 'dekad',
            'value' => 12.5,
            'record_key' => hash('sha256', 'rainfall-test-sort-record'),
        ]);

        $response = $this->actingAs($this->operator())
            ->get(route('statamic.cp.climatehub.rainfall.index', [
                'indicator' => 'r1h',
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-01',
                'sort' => 'value',
                'direction' => 'asc',
            ]))
            ->assertOk()
            ->assertSee('From date')
            ->assertSee('To date');

        $this->assertSame($lowerValueRecord->id, $response->viewData('records')->first()->id);
    }

    public function test_rainfall_injection_stages_a_csv_with_the_local_sync_queue(): void
    {
        config()->set('queue.default', 'sync');
        $record = $this->seedRainfallRecord();
        $csv = "date,pcode,r1h\n2026-09-01,NGA-TEST-01,28.5\n";

        $this->actingAs($this->operator())
            ->from(route('statamic.cp.climatehub.rainfall.index'))
            ->post(route('statamic.cp.climatehub.rainfall.inject'), [
                'rainfall_csv' => UploadedFile::fake()->createWithContent('rainfall.csv', $csv),
            ])
            ->assertRedirect(route('statamic.cp.climatehub.rainfall.index'));

        $this->assertDatabaseHas('imports', [
            'dataset_version_id' => $record->dataset_version_id,
            'status' => 'awaiting_approval',
            'accepted_rows' => 1,
            'rejected_rows' => 0,
        ]);
        $this->assertDatabaseHas('dataset_versions', [
            'id' => $record->dataset_version_id,
            'status' => 'published',
        ]);

        $firstImport = DatasetImport::query()
            ->where('dataset_version_id', $record->dataset_version_id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($this->operator())
            ->post(route('statamic.cp.climatehub.rainfall.inject'), [
                'rainfall_csv' => UploadedFile::fake()->createWithContent('rainfall.csv', $csv),
            ])
            ->assertRedirect(route('statamic.cp.climatehub.rainfall.index'));

        $duplicateImport = DatasetImport::query()
            ->where('dataset_version_id', $record->dataset_version_id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('awaiting_approval', $duplicateImport->status);

        $this->actingAs($this->operator())
            ->post(route('statamic.cp.climatehub.rainfall.imports.approve', $duplicateImport))
            ->assertRedirect(route('statamic.cp.climatehub.rainfall.index'));

        $this->assertDatabaseHas('imports', [
            'id' => $duplicateImport->id,
            'status' => 'rejected_duplicate',
            'error_count' => 1,
        ]);
        $this->assertDatabaseHas('import_errors', [
            'import_id' => $duplicateImport->id,
            'error_code' => 'duplicate_source_file',
            'message' => "Same file as import #{$firstImport->id}.",
        ]);

        $this->actingAs($this->operator())
            ->get(route('statamic.cp.climatehub.rainfall.index'))
            ->assertOk()
            ->assertSee("Same file as import #{$firstImport->id}.");
    }

    public function test_rainfall_import_history_is_paginated_in_sets_of_ten(): void
    {
        $record = $this->seedRainfallRecord();
        $existingImports = DatasetImport::query()->where('dataset_version_id', $record->dataset_version_id)->count();

        foreach (range(1, 11) as $number) {
            DatasetImport::query()->create([
                'dataset_version_id' => $record->dataset_version_id,
                'schema_key' => 'long_admin_period_indicator',
                'source_hash' => hash('sha256', 'rainfall-import-'.$number),
                'status' => 'applied',
                'accepted_rows' => 1,
                'completed_at' => now(),
                'summary' => ['path' => storage_path('app/climatehub/imports/rainfall-import-'.$number.'.csv')],
            ]);
        }

        $response = $this->actingAs($this->operator())
            ->get(route('statamic.cp.climatehub.rainfall.index'))
            ->assertOk()
            ->assertSee('Rainfall import history');

        $this->assertSame(10, $response->viewData('imports')->perPage());
        $this->assertSame($existingImports + 11, $response->viewData('imports')->total());
    }

    public function test_rainfall_correction_creates_an_audit_log(): void
    {
        $record = $this->seedRainfallRecord();

        $this->actingAs($this->operator())
            ->patch(route('statamic.cp.climatehub.rainfall.update', $record), [
                'value' => '31.25',
                'period_date' => '2026-09-01',
                'period_type' => 'dekad',
                'geography_code' => 'NGA-TEST-01',
                'indicator_id' => $record->indicator_id,
                'reason' => 'Corrected against the approved source file.',
            ])
            ->assertRedirect(route('statamic.cp.climatehub.rainfall.index'));

        $this->assertDatabaseHas('admin_period_indicator_values', ['id' => $record->id, 'value' => '31.250000']);
        $this->assertDatabaseHas('admin_period_indicator_value_edits', [
            'admin_period_indicator_value_id' => $record->id,
            'reason' => 'Corrected against the approved source file.',
        ]);
        $this->assertSame(1, AdminPeriodIndicatorValueEdit::query()->count());
    }

    public function test_validated_rainfall_import_is_approved_from_the_rainfall_workspace(): void
    {
        config()->set('queue.default', 'database');
        Queue::fake();
        $record = $this->seedRainfallRecord();
        $import = DatasetImport::query()->create([
            'dataset_version_id' => $record->dataset_version_id,
            'schema_key' => 'long_admin_period_indicator',
            'status' => 'awaiting_approval',
            'accepted_rows' => 1,
            'rejected_rows' => 0,
            'error_count' => 0,
            'summary' => ['path' => storage_path('app/climatehub/imports/rainfall-test.csv')],
        ]);

        $this->actingAs($this->operator())
            ->post(route('statamic.cp.climatehub.rainfall.imports.approve', $import))
            ->assertRedirect(route('statamic.cp.climatehub.rainfall.index'));

        $this->assertDatabaseHas('imports', ['id' => $import->id, 'status' => 'approved']);
        $this->assertDatabaseHas('dataset_versions', [
            'id' => $record->dataset_version_id,
            'status' => 'published',
        ]);
        Queue::assertPushed(\App\Jobs\ApplyApprovedCsvDatasetImport::class, fn ($job) => $job->importId === $import->id);
    }

    private function seedRainfallRecord(): AdminPeriodIndicatorValue
    {
        $source = Source::query()->updateOrCreate(['code' => 'wfp'], ['name' => 'World Food Programme']);
        $dataset = Dataset::query()->updateOrCreate(['code' => 'nigeria_rainfall_subnational'], [
            'slug' => 'nigeria-rainfall-subnational',
            'title' => 'Nigeria Rainfall Indicators at Subnational Level',
            'dataset_type' => 'admin_period_indicator',
            'import_schema' => 'long_admin_period_indicator',
            'default_fact_table' => 'admin_period_indicator_values',
            'source_id' => $source->id,
            'status' => 'active',
        ]);
        $version = DatasetVersion::query()->updateOrCreate(['dataset_id' => $dataset->id, 'version_label' => 'sample-v1'], [
            'status' => 'published',
            'activated_at' => now(),
            'row_count' => 1,
        ]);
        $geography = Geography::query()->updateOrCreate(['code' => 'NGA-TEST-01'], ['name' => 'Test State', 'level' => 1, 'source_pcode' => 'NGA-TEST-01']);
        $indicator = Indicator::query()->updateOrCreate(['code' => 'r1h'], ['name' => 'Monthly rainfall']);

        return AdminPeriodIndicatorValue::query()->updateOrCreate([
            'dataset_version_id' => $version->id,
            'indicator_id' => $indicator->id,
            'geography_code' => $geography->code,
            'period_date' => '2026-09-01',
            'period_type' => 'dekad',
        ], [
            'source_id' => $source->id,
            'geography_id' => $geography->id,
            'value' => 28.5,
            'record_key' => hash('sha256', 'rainfall-test-record'),
        ]);
    }

    private function operator(): User
    {
        $user = User::factory()->create(['name' => 'ClimateHub Operator', 'email' => 'operator-'.uniqid().'@example.test', 'super' => false]);
        DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => 'climatehub_operator']);
        Blink::flush();

        return $user;
    }
}

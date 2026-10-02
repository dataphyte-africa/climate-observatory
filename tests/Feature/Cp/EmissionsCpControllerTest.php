<?php

namespace Tests\Feature\Cp;

use App\Models\CountryYearSectorGasValue;
use App\Models\CountryYearSectorGasValueEdit;
use App\Models\DatasetImport;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Blink;
use Tests\TestCase;

class EmissionsCpControllerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    public function test_emissions_workspace_is_available_from_the_statamic_cp(): void
    {
        $user = User::factory()->create(['super' => false]);
        DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => 'climatehub_operator']);
        Blink::flush();

        $this->actingAs($user)
            ->get(route('statamic.cp.climatehub.emissions.index'))
            ->assertOk()
            ->assertSee('Emissions data')
            ->assertSee('Ingest emissions CSV')
            ->assertSee('Emissions facts')
            ->assertSee('PIK');
    }

    public function test_emissions_fact_correction_creates_an_audit_log(): void
    {
        $user = User::factory()->create(['super' => false]);
        DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => 'climatehub_operator']);
        Blink::flush();
        $fact = CountryYearSectorGasValue::query()->firstOrFail();

        $this->actingAs($user)
            ->patch(route('statamic.cp.climatehub.emissions.update', $fact), [
                'country_code' => $fact->country_code,
                'year' => $fact->year,
                'sector_id' => $fact->sector_id,
                'gas_id' => $fact->gas_id,
                'value' => '12.345',
                'reason' => 'Corrected against the approved source file.',
            ])
            ->assertRedirect(route('statamic.cp.climatehub.emissions.index'));

        $this->assertDatabaseHas('country_year_sector_gas_values', ['id' => $fact->id, 'value' => '12.345000']);
        $this->assertDatabaseHas('country_year_sector_gas_value_edits', ['country_year_sector_gas_value_id' => $fact->id]);
        $this->assertSame(1, CountryYearSectorGasValueEdit::query()->count());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/climatehub/imports'));

        parent::tearDown();
    }

    public function test_emissions_duplicate_file_is_rejected_before_it_is_staged_again(): void
    {
        config()->set('queue.default', 'sync');
        $user = $this->operator();
        $csv = "country,sector,gas,source,1990\nNGA,Energy,CO2,Climate Watch,12.5\n";
        $initialImportCount = DatasetImport::query()->count();

        $this->actingAs($user)
            ->post(route('statamic.cp.climatehub.emissions.inject'), [
                'emissions_csv' => UploadedFile::fake()->createWithContent('primap-original.csv', $csv),
            ])
            ->assertRedirect(route('statamic.cp.climatehub.emissions.index'));

        $firstImport = DatasetImport::query()->latest('id')->firstOrFail();
        $this->assertSame('primap-original.csv', $firstImport->summary['original_filename']);
        $this->assertFileExists(storage_path('app/climatehub/imports/emissions/primap-original.csv'));

        $this->actingAs($user)
            ->post(route('statamic.cp.climatehub.emissions.inject'), [
                'emissions_csv' => UploadedFile::fake()->createWithContent('primap-original.csv', $csv),
            ])
            ->assertSessionHasErrors('emissions_csv');

        $this->assertSame($initialImportCount + 1, DatasetImport::query()->count());
    }

    public function test_existing_emissions_records_are_rejected_during_staging_and_cannot_be_approved(): void
    {
        config()->set('queue.default', 'sync');
        $user = $this->operator();
        $fact = CountryYearSectorGasValue::query()->with(['sector', 'gas', 'source'])->firstOrFail();
        $csv = implode("\n", [
            "country,sector,gas,source,{$fact->year}",
            implode(',', [$fact->country_code, $fact->sector->name, $fact->gas->name, $fact->source?->code, $fact->value]),
            '',
        ]);

        $this->actingAs($user)
            ->post(route('statamic.cp.climatehub.emissions.inject'), [
                'emissions_csv' => UploadedFile::fake()->createWithContent('existing-emissions.csv', $csv),
            ])
            ->assertRedirect(route('statamic.cp.climatehub.emissions.index'));

        $import = DatasetImport::query()->latest('id')->firstOrFail();

        $this->assertSame('validation_failed', $import->status);
        $this->assertSame(0, $import->accepted_rows);
        $this->assertSame(1, $import->rejected_rows);
        $this->assertDatabaseHas('import_errors', [
            'import_id' => $import->id,
            'error_code' => 'existing_dataset_records',
            'message' => 'Same dataset records already exist (1 conflicts).',
        ]);

        $this->actingAs($user)
            ->post(route('statamic.cp.climatehub.emissions.imports.approve', $import))
            ->assertSessionHasErrors('emissions_import');
    }

    public function test_emissions_import_history_is_paginated_in_sets_of_ten(): void
    {
        $fact = CountryYearSectorGasValue::query()->firstOrFail();
        $existingImports = DatasetImport::query()->where('dataset_version_id', $fact->dataset_version_id)->count();

        foreach (range(1, 11) as $number) {
            DatasetImport::query()->create([
                'dataset_version_id' => $fact->dataset_version_id,
                'schema_key' => 'wide_country_sector_gas_year',
                'source_hash' => hash('sha256', 'emissions-import-'.$number),
                'status' => 'applied',
                'accepted_rows' => 1,
                'completed_at' => now(),
                'summary' => ['path' => storage_path('app/climatehub/imports/emissions-import-'.$number.'.csv')],
            ]);
        }

        $response = $this->actingAs($this->operator())
            ->get(route('statamic.cp.climatehub.emissions.index'))
            ->assertOk()
            ->assertSee('Emissions import history');

        $this->assertSame(10, $response->viewData('imports')->perPage());
        $this->assertSame($existingImports + 11, $response->viewData('imports')->total());
    }

    private function operator(): User
    {
        $user = User::factory()->create(['super' => false]);
        DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => 'climatehub_operator']);
        Blink::flush();

        return $user;
    }
}

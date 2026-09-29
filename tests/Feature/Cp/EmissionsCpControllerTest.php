<?php

namespace Tests\Feature\Cp;

use App\Models\User;
use App\Models\CountryYearSectorGasValue;
use App\Models\CountryYearSectorGasValueEdit;
use App\Models\DatasetImport;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

    public function test_emissions_duplicate_file_is_rejected_after_approval_by_the_queue_worker(): void
    {
        config()->set('queue.default', 'sync');
        $user = $this->operator();
        $csv = "country,sector,gas,source,1990\nNGA,Energy,CO2,Climate Watch,12.5\n";

        $this->actingAs($user)
            ->post(route('statamic.cp.climatehub.emissions.inject'), [
                'emissions_csv' => UploadedFile::fake()->createWithContent('emissions.csv', $csv),
            ])
            ->assertRedirect(route('statamic.cp.climatehub.emissions.index'));

        $firstImport = DatasetImport::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->post(route('statamic.cp.climatehub.emissions.imports.approve', $firstImport))
            ->assertRedirect(route('statamic.cp.climatehub.emissions.index'));

        $this->actingAs($user)
            ->post(route('statamic.cp.climatehub.emissions.inject'), [
                'emissions_csv' => UploadedFile::fake()->createWithContent('emissions.csv', $csv),
            ])
            ->assertRedirect(route('statamic.cp.climatehub.emissions.index'));

        $duplicateImport = DatasetImport::query()->latest('id')->firstOrFail();
        $this->assertSame('awaiting_approval', $duplicateImport->status);

        $this->actingAs($user)
            ->post(route('statamic.cp.climatehub.emissions.imports.approve', $duplicateImport))
            ->assertRedirect(route('statamic.cp.climatehub.emissions.index'));

        $this->assertDatabaseHas('imports', [
            'id' => $duplicateImport->id,
            'status' => 'rejected_duplicate',
        ]);
        $this->assertDatabaseHas('import_errors', [
            'import_id' => $duplicateImport->id,
            'error_code' => 'duplicate_source_file',
            'message' => "Same file as import #{$firstImport->id}.",
        ]);
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

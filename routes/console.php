<?php

use App\Imports\CsvDatasetImporter;
use App\Models\Dataset;
use App\Models\DatasetVersion;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('climate:import-csv {dataset_code} {schema} {file} {--version-label=} {--country-code=NGA} {--period-type=} {--source-code=} {--source-name=} {--preview} {--queue}', function () {
    $dataset = Dataset::query()->where('code', $this->argument('dataset_code'))->firstOrFail();
    $versionLabel = $this->option('version-label') ?: now()->format('YmdHis');

    $version = DatasetVersion::firstOrCreate(
        [
            'dataset_id' => $dataset->id,
            'version_label' => $versionLabel,
        ],
        [
            'status' => 'draft',
            'source_filename' => basename($this->argument('file')),
        ]
    );

    $options = [
        'country_code' => $this->option('country-code'),
        'period_type' => $this->option('period-type'),
        'source_code' => $this->option('source-code'),
        'source_name' => $this->option('source-name'),
    ];

    if ($this->option('preview')) {
        $preview = app(CsvDatasetImporter::class)->preview(
            $this->argument('schema'),
            $this->argument('file'),
            $version,
            $options
        );

        $this->info("Previewed schema [{$preview['schema_key']}] for target [{$preview['target_table']}].");
        $this->line("Scanned: {$preview['scanned_rows']}");
        $this->line("Accepted records: {$preview['accepted_rows']}");
        $this->line("Rejected rows: {$preview['rejected_rows']}");

        if ($preview['errors'] !== []) {
            $this->warn('Preview errors:');

            foreach ($preview['errors'] as $error) {
                $this->line("Row {$error['row_number']}: {$error['message']}");
            }
        }

        return 0;
    }

    if ($this->option('queue')) {
        $import = app(CsvDatasetImporter::class)->queue(
            $this->argument('schema'),
            $this->argument('file'),
            $version,
            $options
        );

        $this->info("Queued CSV import [{$import->id}] for dataset [{$dataset->code}] version [{$version->version_label}].");

        return 0;
    }

    $import = app(CsvDatasetImporter::class)->import(
        $this->argument('schema'),
        $this->argument('file'),
        $version,
        $options
    );

    $this->info("Import {$import->id} staged with status [{$import->status}]. An authenticated editor must approve it in the relevant topic workspace before facts are applied.");
    $this->line("Scanned: {$import->scanned_rows}");
    $this->line("Accepted: {$import->accepted_rows}");
    $this->line("Rejected: {$import->rejected_rows}");
})->purpose('Import a climate CSV into the canonical climate data tables.');

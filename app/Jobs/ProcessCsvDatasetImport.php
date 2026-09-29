<?php

namespace App\Jobs;

use App\Imports\CsvDatasetImporter;
use App\Models\DatasetVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCsvDatasetImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly int $importId,
        public readonly int $datasetVersionId,
        public readonly string $schemaKey,
        public readonly string $path,
        public readonly array $options = []
    ) {}

    public function handle(CsvDatasetImporter $importer): void
    {
        $version = DatasetVersion::query()->findOrFail($this->datasetVersionId);

        $importer->import(
            $this->schemaKey,
            $this->path,
            $version,
            array_merge($this->options, [
                'import_id' => $this->importId,
            ])
        );
    }
}

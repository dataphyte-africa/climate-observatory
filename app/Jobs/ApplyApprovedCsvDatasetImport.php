<?php

namespace App\Jobs;

use App\Imports\CsvDatasetImporter;
use App\Models\DatasetImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ApplyApprovedCsvDatasetImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $importId,
        public readonly ?int $userId = null,
    ) {}

    public function handle(CsvDatasetImporter $importer): void
    {
        $importer->apply(DatasetImport::query()->findOrFail($this->importId), $this->userId);
    }
}

<?php

namespace App\Imports;

use App\Imports\Contracts\CsvImportSchema;
use App\Imports\Exceptions\CsvImportException;
use App\Imports\Exceptions\CsvImportRowException;
use App\Jobs\ProcessCsvDatasetImport;
use App\Models\DatasetImport;
use App\Models\DatasetVersion;
use App\Models\ImportError;
use App\Models\ImportStagedRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CsvDatasetImporter
{
    public function __construct(
        private readonly CsvImportRegistry $registry
    ) {}

    public function queue(string $schemaKey, string $path, DatasetVersion $version, array $options = []): DatasetImport
    {
        $schema = $this->registry->resolve($schemaKey);
        $this->assertSchemaMatchesDataset($schemaKey, $schema, $version);

        $import = DatasetImport::create([
            'dataset_version_id' => $version->id,
            'schema_key' => $schemaKey,
            'status' => 'pending',
            'summary' => [
                'path' => $path,
                'target_table' => $schema->targetTable(),
                'queued_at' => now()->toISOString(),
            ],
        ]);

        $version->forceFill([
            'status' => $this->isPublishedVersion($version) ? $version->status : 'queued',
            'source_filename' => basename($path),
        ])->save();

        ProcessCsvDatasetImport::dispatch(
            $import->id,
            $version->id,
            $schemaKey,
            $path,
            $options
        )->onQueue('imports');

        return $import->fresh();
    }

    /**
     * @return array{schema_key: string, target_table: string, headers: array<int, string>, scanned_rows: int, accepted_rows: int, rejected_rows: int, errors: array<int, array<string, mixed>>, sample_records: array<int, array<string, mixed>>}
     */
    public function preview(string $schemaKey, string $path, DatasetVersion $version, array $options = []): array
    {
        [$schema, $normalizedHeader, $handle] = $this->openValidatedCsv($schemaKey, $path, $version);

        $limit = max(1, (int) ($options['limit'] ?? 10));
        $scannedRows = 0;
        $acceptedRows = 0;
        $rejectedRows = 0;
        $errors = [];
        $sampleRecords = [];

        DB::beginTransaction();

        try {
            while (($raw = $this->readCsvRow($handle)) !== false && $scannedRows < $limit) {
                $scannedRows++;

                if ($this->rowIsEmpty($raw)) {
                    continue;
                }

                $row = $this->combineRow($normalizedHeader, $raw);

                try {
                    $records = $schema->transform($row, $version, $options);
                    $acceptedRows += count($records);

                    foreach ($records as $record) {
                        $sampleRecords[] = $this->serializePreviewRecord($record);
                    }
                } catch (CsvImportRowException $exception) {
                    $rejectedRows++;
                    $errors[] = [
                        'row_number' => $scannedRows + 1,
                        'column_name' => $exception->column,
                        'error_code' => 'row_validation_failed',
                        'message' => $exception->getMessage(),
                        'raw_row' => $row,
                    ];
                }
            }
        } finally {
            DB::rollBack();
            fclose($handle);
        }

        return [
            'schema_key' => $schemaKey,
            'target_table' => $schema->targetTable(),
            'columns' => $schema->columns(),
            'headers' => $normalizedHeader,
            'scanned_rows' => $scannedRows,
            'accepted_rows' => $acceptedRows,
            'rejected_rows' => $rejectedRows,
            'errors' => $errors,
            'sample_records' => $sampleRecords,
        ];
    }

    /**
     * Stage and validate a CSV. This intentionally does not write public fact rows.
     */
    public function import(string $schemaKey, string $path, DatasetVersion $version, array $options = []): DatasetImport
    {
        $import = $this->resolveImportRecord($schemaKey, $path, $version, $options);
        $preservePublishedState = $this->isPublishedVersion($version);
        $handle = null;

        try {
            [$schema, $normalizedHeader, $handle] = $this->openValidatedCsv($schemaKey, $path, $version);

            $import->forceFill([
                'status' => 'validating',
                'started_at' => now(),
                'completed_at' => null,
                'approved_at' => null,
                'approved_by_user_id' => null,
                'applied_at' => null,
                'applied_by_user_id' => null,
                'source_hash' => hash_file('sha256', $path),
                'summary' => [
                    'path' => $path,
                    'target_table' => $schema->targetTable(),
                    'headers' => $normalizedHeader,
                ],
            ])->save();

            $import->errors()->delete();
            $import->stagedRecords()->delete();

            $version->forceFill([
                'status' => $preservePublishedState ? $version->status : 'validating',
                'import_started_at' => now(),
                'source_filename' => basename($path),
                'source_hash' => $import->source_hash,
            ])->save();

            $buffer = [];
            $acceptedRows = 0;
            $scannedRows = 0;
            $rejectedRows = 0;
            $seenKeys = [];

            while (($raw = $this->readCsvRow($handle)) !== false) {
                $scannedRows++;

                if ($this->rowIsEmpty($raw)) {
                    continue;
                }

                $row = $this->combineRow($normalizedHeader, $raw);

                try {
                    $records = $schema->transform($row, $version, $options);

                    foreach ($records as $record) {
                        $recordKey = hash('sha256', $schema->naturalKey($record));

                        if (isset($seenKeys[$recordKey])) {
                            $rejectedRows++;
                            $this->recordError($import, $scannedRows + 1, null, 'duplicate_natural_key', 'This CSV repeats an observation with the same version-scoped natural key.', $row);

                            continue;
                        }

                        $seenKeys[$recordKey] = true;
                        $record['record_key'] = $recordKey;
                        $buffer[] = [
                            'import_id' => $import->id,
                            'row_number' => $scannedRows + 1,
                            'record_key' => $recordKey,
                            'record_payload' => json_encode($record, JSON_THROW_ON_ERROR),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $acceptedRows++;
                    }

                    if (count($buffer) >= 1000) {
                        DB::table('import_staged_records')->insert($buffer);
                        $buffer = [];
                    }
                } catch (CsvImportRowException $exception) {
                    $rejectedRows++;

                    $this->recordError($import, $scannedRows + 1, $exception->column, 'row_validation_failed', $exception->getMessage(), $row);
                }
            }

            if ($buffer !== []) {
                DB::table('import_staged_records')->insert($buffer);
            }

            $status = $rejectedRows > 0 ? 'validation_failed' : 'awaiting_approval';

            $import->forceFill([
                'status' => $status,
                'scanned_rows' => $scannedRows,
                'accepted_rows' => $acceptedRows,
                'rejected_rows' => $rejectedRows,
                'error_count' => $rejectedRows,
                'completed_at' => now(),
                'summary' => [
                    'path' => $path,
                    'target_table' => $schema->targetTable(),
                    'headers' => $normalizedHeader,
                ],
            ])->save();

            $version->forceFill([
                'status' => $preservePublishedState ? $version->status : ($rejectedRows > 0 ? 'validated_with_errors' : 'review'),
                'import_completed_at' => now(),
            ])->save();

            return $import->fresh();
        } catch (\Throwable $exception) {
            $this->markFailed($import, $exception, $version);

            throw $exception;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    public function approve(DatasetImport $import, int $userId): DatasetImport
    {
        return DB::transaction(function () use ($import, $userId): DatasetImport {
            $import = DatasetImport::query()->lockForUpdate()->findOrFail($import->id);

            if ($import->status !== 'awaiting_approval' || $import->error_count > 0 || $import->accepted_rows === 0) {
                throw new CsvImportException('Only a fully validated import with staged records can be approved.');
            }

            $import->forceFill([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by_user_id' => $userId,
            ])->save();

            $version = $import->datasetVersion;

            if ($version && ! $this->isPublishedVersion($version)) {
                $version->update(['status' => 'approved']);
            }

            return $import->fresh();
        });
    }

    public function apply(DatasetImport $import, ?int $userId = null): DatasetImport
    {
        $import->loadMissing('datasetVersion.dataset');

        if (! in_array($import->status, ['approved', 'applying', 'applied'], true)) {
            throw new CsvImportException("Import [{$import->id}] has not been approved.");
        }

        if ($import->status === 'applied') {
            return $import;
        }

        $duplicateSourceImport = $this->duplicateSourceImport($import);

        if ($duplicateSourceImport) {
            return $this->rejectDuplicateSourceImport($import, $duplicateSourceImport);
        }

        $schema = $this->registry->resolve($import->schema_key);
        $targetTable = $schema->targetTable();
        $version = $import->datasetVersion;

        if (! $version) {
            throw new CsvImportException("Import [{$import->id}] does not have a dataset version.");
        }

        try {
            $preservePublishedState = $this->isPublishedVersion($version);
            $import->forceFill(['status' => 'applying'])->save();
            $version->forceFill(['status' => $preservePublishedState ? $version->status : 'applying'])->save();

            ImportStagedRecord::query()
                ->where('import_id', $import->id)
                ->orderBy('id')
                ->chunkById(1000, function ($staged) use ($targetTable): void {
                    $records = $staged->map(function (ImportStagedRecord $record): array {
                        return $this->normalizeStagedRecord($record->record_payload);
                    })->all();

                    DB::table($targetTable)->upsert(
                        $records,
                        ['record_key'],
                        $this->updatableColumns($targetTable, $records[0])
                    );
                });

            $rowCount = DB::table($targetTable)
                ->where('dataset_version_id', $version->id)
                ->count();

            $import->forceFill([
                'status' => 'applied',
                'applied_at' => now(),
                'applied_by_user_id' => $userId ?? $import->approved_by_user_id,
            ])->save();

            $version->forceFill([
                'status' => $preservePublishedState ? $version->status : 'review',
                'row_count' => $rowCount,
                'import_completed_at' => now(),
            ])->save();

            return $import->fresh();
        } catch (\Throwable $exception) {
            $this->markFailed($import, $exception, $version);

            throw $exception;
        }
    }

    private function resolveImportRecord(string $schemaKey, string $path, DatasetVersion $version, array $options): DatasetImport
    {
        if (! empty($options['import_id'])) {
            $import = DatasetImport::query()
                ->whereKey($options['import_id'])
                ->where('dataset_version_id', $version->id)
                ->first();

            if (! $import) {
                throw new CsvImportException("Queued import [{$options['import_id']}] was not found for dataset version [{$version->id}].");
            }

            return $import;
        }

        return DatasetImport::create([
            'dataset_version_id' => $version->id,
            'schema_key' => $schemaKey,
            'status' => 'pending',
            'source_hash' => hash_file('sha256', $path),
            'summary' => [
                'path' => $path,
            ],
        ]);
    }

    /**
     * @return array{CsvImportSchema, array<int, string>, resource}
     */
    private function openValidatedCsv(string $schemaKey, string $path, DatasetVersion $version): array
    {
        if (! File::exists($path)) {
            throw new CsvImportException("CSV file does not exist at [{$path}].");
        }

        $schema = $this->registry->resolve($schemaKey);
        $this->assertSchemaMatchesDataset($schemaKey, $schema, $version);

        $handle = fopen($path, 'rb');

        if (! $handle) {
            throw new CsvImportException("Unable to open CSV file [{$path}].");
        }

        $header = $this->readCsvRow($handle);

        if (! $header) {
            fclose($handle);

            throw new CsvImportException('CSV file is empty.');
        }

        $normalizedHeader = array_map([$this, 'normalizeHeader'], $header);
        $this->validateNormalizedHeader($normalizedHeader);
        $schema->validateHeaders($normalizedHeader);

        return [$schema, $normalizedHeader, $handle];
    }

    private function assertSchemaMatchesDataset(string $schemaKey, CsvImportSchema $schema, DatasetVersion $version): void
    {
        $dataset = $version->dataset;

        if (! $dataset) {
            return;
        }

        if ($dataset->import_schema && $dataset->import_schema !== $schemaKey) {
            throw new CsvImportException("Dataset [{$dataset->code}] expects import schema [{$dataset->import_schema}], not [{$schemaKey}].");
        }

        if ($dataset->default_fact_table && $dataset->default_fact_table !== $schema->targetTable()) {
            throw new CsvImportException("Dataset [{$dataset->code}] targets [{$dataset->default_fact_table}], not [{$schema->targetTable()}].");
        }
    }

    /**
     * @param  array<int, string>  $normalizedHeader
     * @param  array<int, string|null>  $raw
     * @return array<string, string|null>
     */
    private function combineRow(array $normalizedHeader, array $raw): array
    {
        $row = [];

        foreach ($normalizedHeader as $index => $key) {
            if ($key === '') {
                continue;
            }

            $row[$key] = $raw[$index] ?? null;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function serializePreviewRecord(array $record): array
    {
        unset($record['created_at'], $record['updated_at']);

        return $record;
    }

    private function markFailed(DatasetImport $import, \Throwable $exception, ?DatasetVersion $version = null): void
    {
        $import->forceFill([
            'status' => 'failed',
            'error_count' => max(1, $import->error_count),
            'completed_at' => now(),
            'summary' => array_merge($import->summary ?? [], [
                'failure_message' => $exception->getMessage(),
            ]),
        ])->save();

        if ($version && ! $this->isPublishedVersion($version)) {
            $version->forceFill([
                'status' => 'import_failed',
                'import_completed_at' => now(),
            ])->save();
        }
    }

    private function duplicateSourceImport(DatasetImport $import): ?DatasetImport
    {
        if (! filled($import->source_hash)) {
            return null;
        }

        return DatasetImport::query()
            ->where('dataset_version_id', $import->dataset_version_id)
            ->where('source_hash', $import->source_hash)
            ->where('id', '<', $import->id)
            ->oldest('id')
            ->first();
    }

    private function rejectDuplicateSourceImport(DatasetImport $import, DatasetImport $original): DatasetImport
    {
        $message = "Same file as import #{$original->id}.";

        $import->forceFill([
            'status' => 'rejected_duplicate',
            'error_count' => max(1, $import->error_count),
            'completed_at' => now(),
            'summary' => array_merge($import->summary ?? [], [
                'duplicate_of_import_id' => $original->id,
                'failure_message' => $message,
            ]),
        ])->save();

        $this->recordError($import, 0, null, 'duplicate_source_file', $message, []);

        return $import->fresh();
    }

    private function isPublishedVersion(DatasetVersion $version): bool
    {
        return in_array($version->status, ['published', 'active'], true);
    }

    /** @param array<string, mixed> $rawRow */
    private function recordError(DatasetImport $import, int $rowNumber, ?string $column, string $code, string $message, array $rawRow): void
    {
        ImportError::create([
            'import_id' => $import->id,
            'row_number' => $rowNumber,
            'column_name' => $column,
            'error_code' => $code,
            'message' => $message,
            'raw_row' => $rawRow,
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<int, string>
     */
    private function updatableColumns(string $targetTable, array $record): array
    {
        return array_values(array_filter(array_keys($record), fn (string $column): bool => ! in_array($column, [
            'id',
            'record_key',
            'created_at',
            'dataset_version_id',
        ], true)));
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', trim($header)) ?? trim($header);
        $header = strtolower($header);
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;

        return trim($header, '_');
    }

    /** @param resource $handle */
    private function readCsvRow($handle): array|false
    {
        return fgetcsv($handle, 0, ',', '"', '\\');
    }

    /** @param array<string, mixed> $record */
    private function normalizeStagedRecord(array $record): array
    {
        foreach (['created_at', 'updated_at'] as $column) {
            if (isset($record[$column])) {
                $record[$column] = Carbon::parse((string) $record[$column])->toDateTimeString();
            }
        }

        return $record;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function validateNormalizedHeader(array $headers): void
    {
        $filled = array_values(array_filter($headers, fn (string $header): bool => $header !== ''));

        if ($filled === []) {
            throw new CsvImportException('CSV header must include at least one named column.');
        }

        $counts = array_count_values($filled);

        foreach ($counts as $header => $count) {
            if ($count > 1) {
                throw new CsvImportException("CSV header contains duplicate column [{$header}].");
            }
        }
    }
}

<?php

namespace App\Imports\Schemas;

use App\Imports\Contracts\CsvImportSchema;
use App\Imports\Exceptions\CsvImportException;
use App\Imports\Exceptions\CsvImportRowException;
use App\Models\DatasetVersion;
use App\Models\DocumentType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CountryDocumentSchema implements CsvImportSchema
{
    public function key(): string
    {
        return 'country_document';
    }

    public function targetTable(): string
    {
        return 'country_documents';
    }

    public function columns(): array
    {
        return ['required' => ['country', 'document_code', 'title'], 'optional' => ['document_type', 'submission_date', 'status', 'summary', 'source']];
    }

    public function validateHeaders(array $headers): void
    {
        foreach (['country', 'document_code', 'title'] as $column) {
            if (! in_array($column, $headers, true)) {
                throw new CsvImportException("CSV header is missing a required [{$column}] column.");
            }
        }
    }

    public function transform(array $row, DatasetVersion $version, array $options = []): array
    {
        foreach (['country', 'document_code', 'title'] as $column) {
            if (blank($row[$column] ?? null)) {
                throw new CsvImportRowException($column, "{$column} is required.");
            }
        }
        $documentType = filled($row['document_type'] ?? null) ? DocumentType::firstOrCreate(['code' => Str::slug((string) $row['document_type'], '_')], ['name' => Str::headline((string) $row['document_type'])]) : null;
        $date = blank($row['submission_date'] ?? null) ? null : Carbon::parse((string) $row['submission_date'])->toDateString();
        $now = now();

        return [[
            'dataset_version_id' => $version->id, 'source_id' => $version->dataset?->source_id, 'document_type_id' => $documentType?->id,
            'country_code' => Str::upper(trim((string) $row['country'])), 'document_code' => trim((string) $row['document_code']), 'title' => trim((string) $row['title']),
            'submission_date' => $date, 'status' => filled($row['status'] ?? null) ? trim((string) $row['status']) : 'published', 'summary' => $row['summary'] ?? null,
            'payload' => json_encode(['schema' => $this->key()], JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now,
        ]];
    }

    public function naturalKey(array $record): string
    {
        return implode("\x1F", [$this->targetTable(), $record['dataset_version_id'], $record['country_code'], $record['document_code']]);
    }
}

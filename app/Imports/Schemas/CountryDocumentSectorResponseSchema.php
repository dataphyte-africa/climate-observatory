<?php

namespace App\Imports\Schemas;

use App\Imports\Contracts\CsvImportSchema;
use App\Imports\Exceptions\CsvImportException;
use App\Imports\Exceptions\CsvImportRowException;
use App\Models\DatasetVersion;
use App\Models\DocumentType;
use App\Models\Sector;
use Illuminate\Support\Str;

class CountryDocumentSectorResponseSchema implements CsvImportSchema
{
    public function key(): string
    {
        return 'country_document_sector_response';
    }

    public function targetTable(): string
    {
        return 'country_document_sector_responses';
    }

    public function columns(): array
    {
        return ['required' => ['country', 'document_code', 'question_code'], 'optional' => ['document_type', 'sector', 'subsector_code', 'response_text', 'source']];
    }

    public function validateHeaders(array $headers): void
    {
        foreach (['country', 'document_code', 'question_code'] as $column) {
            if (! in_array($column, $headers, true)) {
                throw new CsvImportException("CSV header is missing a required [{$column}] column.");
            }
        }
    }

    public function transform(array $row, DatasetVersion $version, array $options = []): array
    {
        foreach (['country', 'document_code', 'question_code'] as $column) {
            if (blank($row[$column] ?? null)) {
                throw new CsvImportRowException($column, "{$column} is required.");
            }
        }
        $type = filled($row['document_type'] ?? null) ? DocumentType::firstOrCreate(['code' => Str::slug((string) $row['document_type'], '_')], ['name' => Str::headline((string) $row['document_type'])]) : null;
        $sector = filled($row['sector'] ?? null) ? Sector::firstOrCreate(['code' => Str::slug((string) $row['sector'], '_')], ['name' => Str::headline((string) $row['sector'])]) : null;
        $now = now();

        return [[
            'dataset_version_id' => $version->id, 'source_id' => $version->dataset?->source_id, 'document_type_id' => $type?->id, 'sector_id' => $sector?->id,
            'country_code' => Str::upper(trim((string) $row['country'])), 'document_code' => trim((string) $row['document_code']), 'question_code' => trim((string) $row['question_code']),
            'subsector_code' => filled($row['subsector_code'] ?? null) ? trim((string) $row['subsector_code']) : null, 'response_text' => $row['response_text'] ?? null,
            'metadata' => json_encode(['schema' => $this->key()], JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now,
        ]];
    }

    public function naturalKey(array $record): string
    {
        return implode("\x1F", [$this->targetTable(), $record['dataset_version_id'], $record['country_code'], $record['document_code'], $record['question_code'], $record['subsector_code'] ?? '']);
    }
}

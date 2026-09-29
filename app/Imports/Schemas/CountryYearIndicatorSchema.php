<?php

namespace App\Imports\Schemas;

use App\Imports\Contracts\CsvImportSchema;
use App\Imports\Exceptions\CsvImportException;
use App\Imports\Exceptions\CsvImportRowException;
use App\Models\DatasetVersion;
use App\Models\Indicator;
use Illuminate\Support\Str;

class CountryYearIndicatorSchema implements CsvImportSchema
{
    public function key(): string
    {
        return 'country_year_indicator';
    }

    public function targetTable(): string
    {
        return 'country_year_indicator_values';
    }

    public function columns(): array
    {
        return ['required' => ['country', 'indicator', 'year', 'value'], 'optional' => ['source']];
    }

    public function validateHeaders(array $headers): void
    {
        foreach (['country', 'indicator', 'year', 'value'] as $column) {
            if (! in_array($column, $headers, true)) {
                throw new CsvImportException("CSV header is missing a required [{$column}] column.");
            }
        }
    }

    public function transform(array $row, DatasetVersion $version, array $options = []): array
    {
        foreach (['country', 'indicator', 'year', 'value'] as $column) {
            if (blank($row[$column] ?? null)) {
                throw new CsvImportRowException($column, "{$column} is required.");
            }
        }
        if (! ctype_digit((string) $row['year']) || ! is_numeric($row['value'])) {
            throw new CsvImportRowException('value', 'Year must be an integer and value must be numeric.');
        }
        $indicator = Indicator::firstOrCreate(['code' => Str::slug((string) $row['indicator'], '_')], ['name' => Str::headline((string) $row['indicator']), 'grain' => 'country_year_indicator', 'value_type' => 'number']);
        $now = now();

        return [[
            'dataset_version_id' => $version->id, 'source_id' => $version->dataset?->source_id,
            'indicator_id' => $indicator->id, 'country_code' => Str::upper(trim((string) $row['country'])),
            'year' => (int) $row['year'], 'value' => (float) $row['value'],
            'metadata' => json_encode(['schema' => $this->key()], JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now,
        ]];
    }

    public function naturalKey(array $record): string
    {
        return implode("\x1F", [$this->targetTable(), $record['dataset_version_id'], $record['source_id'] ?? 0, $record['indicator_id'], $record['country_code'], $record['year']]);
    }
}

<?php

namespace App\Imports\Schemas;

use App\Imports\Contracts\CsvImportSchema;
use App\Imports\Exceptions\CsvImportException;
use App\Imports\Exceptions\CsvImportRowException;
use App\Models\DatasetVersion;
use App\Models\Geography;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class EventImpactSchema implements CsvImportSchema
{
    public function key(): string
    {
        return 'event_impact';
    }

    public function targetTable(): string
    {
        return 'event_impacts';
    }

    public function columns(): array
    {
        return ['required' => ['country', 'event_date', 'event_type', 'metric_code'], 'optional' => ['event_name', 'geography_code', 'value', 'unit', 'source']];
    }

    public function validateHeaders(array $headers): void
    {
        foreach (['country', 'event_date', 'event_type', 'metric_code'] as $column) {
            if (! in_array($column, $headers, true)) {
                throw new CsvImportException("CSV header is missing a required [{$column}] column.");
            }
        }
    }

    public function transform(array $row, DatasetVersion $version, array $options = []): array
    {
        foreach (['country', 'event_date', 'event_type', 'metric_code'] as $column) {
            if (blank($row[$column] ?? null)) {
                throw new CsvImportRowException($column, "{$column} is required.");
            }
        }
        if (filled($row['value'] ?? null) && ! is_numeric($row['value'])) {
            throw new CsvImportRowException('value', 'Value must be numeric when provided.');
        }
        $code = filled($row['geography_code'] ?? null) ? trim((string) $row['geography_code']) : null;
        $geography = $code ? Geography::where('code', $code)->first() : null;
        $unit = filled($row['unit'] ?? null) ? Unit::firstOrCreate(['code' => Str::slug((string) $row['unit'], '_')], ['name' => Str::headline((string) $row['unit'])]) : null;
        $now = now();

        return [[
            'dataset_version_id' => $version->id, 'source_id' => $version->dataset?->source_id, 'geography_id' => $geography?->id, 'unit_id' => $unit?->id,
            'country_code' => Str::upper(trim((string) $row['country'])), 'geography_code' => $code, 'event_date' => Carbon::parse((string) $row['event_date'])->toDateString(),
            'event_type' => trim((string) $row['event_type']), 'event_name' => $row['event_name'] ?? null, 'metric_code' => trim((string) $row['metric_code']),
            'value' => filled($row['value'] ?? null) ? (float) $row['value'] : null, 'metadata' => json_encode(['schema' => $this->key()], JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now,
        ]];
    }

    public function naturalKey(array $record): string
    {
        return implode("\x1F", [$this->targetTable(), $record['dataset_version_id'], $record['country_code'], $record['geography_code'] ?? '', $record['event_date'], $record['event_type'], $record['event_name'] ?? '', $record['metric_code']]);
    }
}

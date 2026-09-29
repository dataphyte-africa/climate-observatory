<?php

namespace App\Imports\Schemas;

use App\Imports\Contracts\CsvImportSchema;
use App\Imports\Exceptions\CsvImportException;
use App\Imports\Exceptions\CsvImportRowException;
use App\Models\DatasetVersion;
use App\Models\Gas;
use App\Models\Sector;
use App\Models\Source;
use Illuminate\Support\Str;

class WideCountrySectorGasYearSchema implements CsvImportSchema
{
    public function key(): string
    {
        return 'wide_country_sector_gas_year';
    }

    public function targetTable(): string
    {
        return 'country_year_sector_gas_values';
    }

    public function columns(): array
    {
        return [
            'required' => ['country', 'sector', 'gas', 'one or more YYYY year columns'],
            'optional' => ['source'],
        ];
    }

    public function validateHeaders(array $headers): void
    {
        $required = [
            'country' => ['country', 'country_code', 'iso', 'state'],
            'sector' => ['sector'],
            'gas' => ['gas'],
        ];

        foreach ($required as $label => $aliases) {
            if (! $this->hasAnyHeader($headers, $aliases)) {
                throw new CsvImportException("CSV header is missing a required [{$label}] column.");
            }
        }

        $hasYearColumn = collect($headers)->contains(fn (string $header): bool => (bool) preg_match('/^\d{4}$/', $header));

        if (! $hasYearColumn) {
            throw new CsvImportException('CSV header must include at least one four-digit year column.');
        }
    }

    public function transform(array $row, DatasetVersion $version, array $options = []): array
    {
        $country = $this->value($row, ['country', 'country_code', 'iso', 'state']);
        $sectorName = $this->value($row, ['sector']);
        $gasName = $this->value($row, ['gas']);
        $sourceName = $this->value($row, ['source']);

        if (! $country) {
            throw new CsvImportRowException('country', 'Country code or country column is required.');
        }

        if (! $sectorName) {
            throw new CsvImportRowException('sector', 'Sector column is required.');
        }

        if (! $gasName) {
            throw new CsvImportRowException('gas', 'Gas column is required.');
        }

        $source = $sourceName
            ? Source::firstOrCreate(
                ['code' => Str::slug($sourceName, '_')],
                ['name' => $sourceName]
            )
            : $version->dataset?->source;

        $sector = Sector::firstOrCreate(
            ['code' => Str::slug($sectorName, '_')],
            ['name' => $sectorName]
        );

        $gas = Gas::firstOrCreate(
            ['code' => Str::slug($gasName, '_')],
            ['name' => $gasName]
        );

        $records = [];
        $now = now();

        foreach ($row as $key => $value) {
            if (! preg_match('/^\d{4}$/', $key)) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            if (! is_numeric($value)) {
                throw new CsvImportRowException($key, 'Year value must be numeric.');
            }

            $records[] = [
                'dataset_version_id' => $version->id,
                'source_id' => $source?->id,
                'sector_id' => $sector->id,
                'gas_id' => $gas->id,
                'country_code' => Str::upper(trim((string) $country)),
                'year' => (int) $key,
                'value' => (float) $value,
                'metadata' => json_encode([
                    'schema' => $this->key(),
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($records === []) {
            throw new CsvImportRowException(null, 'No year columns were found in the row.');
        }

        return $records;
    }

    public function naturalKey(array $record): string
    {
        return implode("\x1F", [
            $this->targetTable(),
            $record['dataset_version_id'],
            $record['source_id'] ?? 0,
            $record['sector_id'] ?? 0,
            $record['gas_id'] ?? 0,
            $record['country_code'],
            $record['year'],
        ]);
    }

    private function value(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $keys
     */
    private function hasAnyHeader(array $headers, array $keys): bool
    {
        foreach ($keys as $key) {
            if (in_array($key, $headers, true)) {
                return true;
            }
        }

        return false;
    }
}

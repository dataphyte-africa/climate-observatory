<?php

namespace App\Imports\Schemas;

use App\Imports\Contracts\CsvImportSchema;
use App\Imports\Exceptions\CsvImportException;
use App\Imports\Exceptions\CsvImportRowException;
use App\Models\DatasetVersion;
use App\Models\Geography;
use App\Models\Indicator;
use App\Models\Source;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class LongAdminPeriodIndicatorSchema implements CsvImportSchema
{
    private const RESERVED_KEYS = [
        'date',
        'period_date',
        'period_type',
        'adm_level',
        'adm_id',
        'geography_code',
        'pcode',
        'geography_name',
        'name',
        'version',
        'n_pixels',
        'country',
        'country_code',
    ];

    public function key(): string
    {
        return 'long_admin_period_indicator';
    }

    public function targetTable(): string
    {
        return 'admin_period_indicator_values';
    }

    public function columns(): array
    {
        return [
            'required' => ['date or period_date', 'pcode or geography_code', 'one or more numeric indicator columns'],
            'optional' => ['period_type', 'adm_level', 'adm_id', 'geography_name', 'version', 'n_pixels'],
        ];
    }

    public function validateHeaders(array $headers): void
    {
        if (! $this->hasAnyHeader($headers, ['date', 'period_date'])) {
            throw new CsvImportException('CSV header is missing a required [date] or [period_date] column.');
        }

        if (! $this->hasAnyHeader($headers, ['pcode', 'geography_code', 'adm_pcode', 'code'])) {
            throw new CsvImportException('CSV header is missing a required PCODE or geography code column.');
        }

        $measureColumns = array_filter(
            $headers,
            fn (string $header): bool => $header !== '' && ! in_array($header, self::RESERVED_KEYS, true)
        );

        if ($measureColumns === []) {
            throw new CsvImportException('CSV header must include at least one indicator measure column.');
        }
    }

    public function transform(array $row, DatasetVersion $version, array $options = []): array
    {
        $dateValue = $this->value($row, ['date', 'period_date']);
        $geographyCode = $this->value($row, ['pcode', 'geography_code', 'adm_pcode', 'code']);

        if (! $dateValue) {
            throw new CsvImportRowException('date', 'Date column is required.');
        }

        if (! $geographyCode) {
            throw new CsvImportRowException('geography_code', 'PCODE or geography code column is required.');
        }

        try {
            $periodDate = Carbon::parse($dateValue)->toDateString();
        } catch (\Throwable) {
            throw new CsvImportRowException('date', 'Date column must be parseable.');
        }

        $periodType = $this->detectPeriodType($row, $periodDate, $options);

        $geography = Geography::firstOrCreate(
            ['code' => trim((string) $geographyCode)],
            [
                'name' => $this->value($row, ['geography_name', 'name']) ?: trim((string) $geographyCode),
                'country_code' => $options['country_code'] ?? 'NGA',
                'level' => (int) ($row['adm_level'] ?? 0),
                'type' => 'administrative',
                'metadata' => [
                    'adm_id' => $row['adm_id'] ?? null,
                ],
            ]
        );

        $source = null;

        if ($version->dataset?->source_id) {
            $source = $version->dataset->source;
        } elseif (! empty($options['source_code'])) {
            $source = Source::firstOrCreate(
                ['code' => $options['source_code']],
                ['name' => $options['source_name'] ?? Str::headline($options['source_code'])]
            );
        }

        $records = [];
        $now = now();

        foreach ($row as $key => $value) {
            if (in_array($key, self::RESERVED_KEYS, true)) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            if (! is_numeric($value)) {
                continue;
            }

            $indicator = Indicator::firstOrCreate(
                ['code' => $key],
                [
                    'name' => Str::headline($key),
                    'grain' => 'admin_period_indicator',
                    'value_type' => 'number',
                ]
            );

            $records[] = [
                'dataset_version_id' => $version->id,
                'source_id' => $source?->id,
                'geography_id' => $geography->id,
                'indicator_id' => $indicator->id,
                'geography_code' => $geography->code,
                'period_date' => $periodDate,
                'period_type' => $periodType,
                'value' => (float) $value,
                'metadata' => json_encode([
                    'adm_level' => $row['adm_level'] ?? null,
                    'adm_id' => $row['adm_id'] ?? null,
                    'n_pixels' => $row['n_pixels'] ?? null,
                    'version' => $row['version'] ?? null,
                    'schema' => $this->key(),
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($records === []) {
            throw new CsvImportRowException(null, 'No numeric indicator columns were found in the row.');
        }

        return $records;
    }

    public function naturalKey(array $record): string
    {
        return implode("\x1F", [
            $this->targetTable(),
            $record['dataset_version_id'],
            $record['indicator_id'],
            $record['geography_code'],
            $record['period_date'],
            $record['period_type'],
        ]);
    }

    private function detectPeriodType(array $row, string $periodDate, array $options): string
    {
        if (! empty($options['period_type'])) {
            return $options['period_type'];
        }

        if (! empty($row['period_type'])) {
            return (string) $row['period_type'];
        }

        $day = (int) Carbon::parse($periodDate)->format('d');

        if (in_array($day, [1, 11, 21], true)) {
            return 'dekad';
        }

        return 'date';
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

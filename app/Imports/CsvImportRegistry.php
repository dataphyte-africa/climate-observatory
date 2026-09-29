<?php

namespace App\Imports;

use App\Imports\Contracts\CsvImportSchema;
use App\Imports\Exceptions\CsvImportException;
use App\Imports\Schemas\CountryDocumentSchema;
use App\Imports\Schemas\CountryDocumentSectorResponseSchema;
use App\Imports\Schemas\CountryYearIndicatorSchema;
use App\Imports\Schemas\EventImpactSchema;
use App\Imports\Schemas\LongAdminPeriodIndicatorSchema;
use App\Imports\Schemas\WideCountrySectorGasYearSchema;

class CsvImportRegistry
{
    /**
     * @var array<string, class-string<CsvImportSchema>>
     */
    private array $schemas = [
        'wide_country_sector_gas_year' => WideCountrySectorGasYearSchema::class,
        'long_admin_period_indicator' => LongAdminPeriodIndicatorSchema::class,
        'country_year_indicator' => CountryYearIndicatorSchema::class,
        'country_document' => CountryDocumentSchema::class,
        'country_document_sector_response' => CountryDocumentSectorResponseSchema::class,
        'event_impact' => EventImpactSchema::class,
    ];

    public function resolve(string $key): CsvImportSchema
    {
        $class = $this->schemas[$key] ?? null;

        if (! $class) {
            throw new CsvImportException("Unknown import schema [{$key}].");
        }

        return app($class);
    }
}

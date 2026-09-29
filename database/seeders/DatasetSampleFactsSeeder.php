<?php

namespace Database\Seeders;

use App\Models\AdminPeriodIndicatorValue;
use App\Models\CountryDocument;
use App\Models\CountryDocumentSectorResponse;
use App\Models\CountryYearIndicatorValue;
use App\Models\CountryYearSectorGasValue;
use App\Models\Dataset;
use App\Models\DatasetImport;
use App\Models\DocumentType;
use App\Models\EventImpact;
use App\Models\Gas;
use App\Models\Geography;
use App\Models\ImportError;
use App\Models\Indicator;
use App\Models\Sector;
use App\Models\Source;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatasetSampleFactsSeeder extends Seeder
{
    public function run(): void
    {
        $historicalDataset = Dataset::where('code', 'climatewatch_historical_emissions')->first();
        $rainfallDataset = Dataset::where('code', 'nigeria_rainfall_subnational')->first();
        $riskDataset = Dataset::where('code', 'nigeria_risk_assessment_indicators')->first();
        $countryIndicatorDataset = Dataset::where('code', 'nigeria_country_year_indicators')->first();
        $countryDocumentDataset = Dataset::where('code', 'nigeria_climate_policy_documents')->first();
        $documentResponseDataset = Dataset::where('code', 'nigeria_document_sector_responses')->first();
        $eventImpactDataset = Dataset::where('code', 'nigeria_flood_event_impacts')->first();

        $historicalVersion = $historicalDataset?->versions()->where('version_label', 'sample-v1')->first();
        $rainfallVersion = $rainfallDataset?->versions()->where('version_label', 'sample-v1')->first();
        $riskVersion = $riskDataset?->versions()->where('version_label', 'sample-v1')->first();
        $countryIndicatorVersion = $countryIndicatorDataset?->versions()->where('version_label', 'sample-v1')->first();
        $countryDocumentVersion = $countryDocumentDataset?->versions()->where('version_label', 'sample-v1')->first();
        $documentResponseVersion = $documentResponseDataset?->versions()->where('version_label', 'sample-v1')->first();
        $eventImpactVersion = $eventImpactDataset?->versions()->where('version_label', 'sample-v1')->first();

        $agriculture = Sector::where('code', 'agriculture')->first();
        $buildings = Sector::where('code', 'buildings')->first();
        $agLandPct = Indicator::where('code', 'ag_land_pct')->first();
        $waterWithdrawal = Indicator::where('code', 'water_withdrawal_pc')->first();
        $ndc = DocumentType::where('code', 'ndc')->first();
        $nap = DocumentType::where('code', 'nap')->first();
        $ng002 = Geography::where('code', 'NG002')->first();
        $ng025 = Geography::where('code', 'NG025')->first();
        $mapSampleGeographies = Geography::query()
            ->whereIn('code', ['NG001', 'NG002', 'NG003', 'NG005', 'NG025'])
            ->get()
            ->keyBy('code');
        $mapSampleLgas = Geography::query()
            ->where('level', 2)
            ->whereIn('parent_id', $mapSampleGeographies->pluck('id'))
            ->orderBy('code')
            ->limit(5)
            ->get();
        $count = Unit::where('code', 'count')->first();

        if ($historicalVersion) {
            CountryYearSectorGasValue::query()
                ->where('dataset_version_id', $historicalVersion->id)
                ->delete();

            $emissions = $this->sourceDerivedEmissionSample($historicalVersion->id, $historicalDataset->source_id);

            CountryYearSectorGasValue::insert($emissions);
            $historicalVersion->update(['row_count' => count($emissions)]);
        }

        if ($rainfallVersion) {
            $rainfallIndicators = Indicator::query()
                ->whereIn('code', ['rfh', 'rfh_avg', 'r1h', 'r1h_avg', 'rfq'])
                ->orderBy('code')
                ->get()
                ->values();

            AdminPeriodIndicatorValue::query()
                ->where('dataset_version_id', $rainfallVersion->id)
                ->delete();

            $rainfallValues = [];
            $rainfallGeographies = $mapSampleGeographies
                ->concat($mapSampleLgas)
                ->unique('id')
                ->take(10)
                ->values();

            foreach (range(0, 3) as $periodIndex) {
                $periodDate = now()->setDate(2026, 4, 11)->subDays($periodIndex * 10)->toDateString();

                foreach ($rainfallGeographies as $geographyIndex => $geography) {
                    foreach ($rainfallIndicators as $indicatorIndex => $indicator) {
                        $rainfallValues[] = $this->adminIndicatorSample(
                            $rainfallVersion->id,
                            $rainfallDataset->source_id,
                            $geography,
                            $indicator->id,
                            $periodDate,
                            'dekad',
                            8.5 + ($geographyIndex * 1.75) + ($periodIndex * 0.2) + ($indicatorIndex * 4.5),
                        );
                    }
                }
            }

            AdminPeriodIndicatorValue::insert($rainfallValues);
            $rainfallVersion->update(['row_count' => count($rainfallValues)]);
        }

        if ($riskVersion) {
            $floodExposure = Indicator::where('code', 'flood_exposure_score')->first();

            AdminPeriodIndicatorValue::query()
                ->where('dataset_version_id', $riskVersion->id)
                ->delete();

            $riskValues = [];
            foreach (['NG001' => 0.38, 'NG002' => 0.61, 'NG003' => 0.44, 'NG005' => 0.72, 'NG025' => 0.29] as $code => $value) {
                $geography = $mapSampleGeographies->get($code);

                if ($geography && $floodExposure) {
                    $riskValues[] = $this->adminIndicatorSample(
                        $riskVersion->id,
                        $riskDataset->source_id,
                        $geography,
                        $floodExposure->id,
                        '2024-01-01',
                        'assessment',
                        $value,
                    );
                }
            }

            foreach ($mapSampleLgas as $index => $geography) {
                if ($floodExposure) {
                    $riskValues[] = $this->adminIndicatorSample(
                        $riskVersion->id,
                        $riskDataset->source_id,
                        $geography,
                        $floodExposure->id,
                        '2024-01-01',
                        'assessment',
                        0.24 + ($index * 0.09),
                    );
                }
            }

            AdminPeriodIndicatorValue::insert($riskValues);
            $riskVersion->update(['row_count' => count($riskValues)]);
        }

        if ($countryIndicatorVersion) {
            CountryYearIndicatorValue::query()
                ->where('dataset_version_id', $countryIndicatorVersion->id)
                ->delete();

            CountryYearIndicatorValue::insert([
                [
                    'dataset_version_id' => $countryIndicatorVersion->id,
                    'source_id' => $countryIndicatorDataset->source_id,
                    'indicator_id' => $agLandPct?->id,
                    'country_code' => 'NGA',
                    'year' => 2020,
                    'value' => 69.400000,
                    'metadata' => json_encode(['sample' => true]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'dataset_version_id' => $countryIndicatorVersion->id,
                    'source_id' => $countryIndicatorDataset->source_id,
                    'indicator_id' => $waterWithdrawal?->id,
                    'country_code' => 'NGA',
                    'year' => 2021,
                    'value' => 130.250000,
                    'metadata' => json_encode(['sample' => true]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $countryIndicatorVersion->update(['row_count' => 2]);
        }

        if ($countryDocumentVersion) {
            CountryDocument::query()
                ->where('dataset_version_id', $countryDocumentVersion->id)
                ->delete();

            CountryDocument::insert([
                [
                    'dataset_version_id' => $countryDocumentVersion->id,
                    'source_id' => $countryDocumentDataset->source_id,
                    'document_type_id' => $ndc?->id,
                    'country_code' => 'NGA',
                    'document_code' => 'nga-ndc-2021',
                    'title' => 'Nigeria Updated Nationally Determined Contribution',
                    'submission_date' => '2021-07-30',
                    'status' => 'published',
                    'summary' => 'Updated national commitment document with mitigation and adaptation sections.',
                    'payload' => json_encode(['sample' => true, 'version' => '2021']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'dataset_version_id' => $countryDocumentVersion->id,
                    'source_id' => $countryDocumentDataset->source_id,
                    'document_type_id' => $nap?->id,
                    'country_code' => 'NGA',
                    'document_code' => 'nga-nap-draft',
                    'title' => 'Nigeria National Adaptation Plan Draft',
                    'submission_date' => '2024-05-12',
                    'status' => 'draft',
                    'summary' => 'Draft adaptation planning document for cross-sector climate resilience.',
                    'payload' => json_encode(['sample' => true, 'version' => 'draft']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $countryDocumentVersion->update(['row_count' => 2]);
        }

        if ($documentResponseVersion) {
            CountryDocumentSectorResponse::query()
                ->where('dataset_version_id', $documentResponseVersion->id)
                ->delete();

            CountryDocumentSectorResponse::insert([
                [
                    'dataset_version_id' => $documentResponseVersion->id,
                    'source_id' => $documentResponseDataset->source_id,
                    'document_type_id' => $ndc?->id,
                    'sector_id' => $agriculture?->id,
                    'country_code' => 'NGA',
                    'document_code' => 'nga-ndc-2021',
                    'question_code' => 'adapt_ag_01',
                    'subsector_code' => 'crop_systems',
                    'response_text' => 'Scale climate-smart agriculture practices and extension support in vulnerable farming zones.',
                    'metadata' => json_encode(['sample' => true]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'dataset_version_id' => $documentResponseVersion->id,
                    'source_id' => $documentResponseDataset->source_id,
                    'document_type_id' => $ndc?->id,
                    'sector_id' => $buildings?->id,
                    'country_code' => 'NGA',
                    'document_code' => 'nga-ndc-2021',
                    'question_code' => 'mit_build_02',
                    'subsector_code' => 'public_buildings',
                    'response_text' => 'Improve energy efficiency standards in public building retrofits.',
                    'metadata' => json_encode(['sample' => true]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $documentResponseVersion->update(['row_count' => 2]);
        }

        if ($eventImpactVersion) {
            EventImpact::query()
                ->where('dataset_version_id', $eventImpactVersion->id)
                ->delete();

            EventImpact::insert([
                [
                    'dataset_version_id' => $eventImpactVersion->id,
                    'source_id' => $eventImpactDataset->source_id,
                    'geography_id' => $ng002?->id ?? null,
                    'unit_id' => $count?->id,
                    'country_code' => 'NGA',
                    'geography_code' => 'NG002',
                    'event_date' => '2024-09-18',
                    'event_type' => 'flood',
                    'event_name' => 'September Flooding Wave',
                    'metric_code' => 'people_displaced',
                    'value' => 12500,
                    'metadata' => json_encode(['sample' => true]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'dataset_version_id' => $eventImpactVersion->id,
                    'source_id' => $eventImpactDataset->source_id,
                    'geography_id' => $ng025?->id,
                    'unit_id' => $count?->id,
                    'country_code' => 'NGA',
                    'geography_code' => 'NG025',
                    'event_date' => '2024-09-18',
                    'event_type' => 'flood',
                    'event_name' => 'September Flooding Wave',
                    'metric_code' => 'houses_affected',
                    'value' => 3200,
                    'metadata' => json_encode(['sample' => true]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $eventImpactVersion->update(['row_count' => 2]);
        }

        if ($historicalVersion) {
            $successImport = DatasetImport::updateOrCreate(
                [
                    'dataset_version_id' => $historicalVersion->id,
                    'schema_key' => 'wide_country_sector_gas_year',
                ],
                [
                    'status' => 'completed',
                    'scanned_rows' => 2,
                    'accepted_rows' => 2,
                    'rejected_rows' => 0,
                    'warning_count' => 0,
                    'error_count' => 0,
                    'started_at' => now()->subMinute(),
                    'completed_at' => now(),
                    'summary' => ['sample' => true],
                ]
            );

            ImportError::query()->where('import_id', $successImport->id)->delete();
        }

        if ($rainfallVersion) {
            $errorImport = DatasetImport::updateOrCreate(
                [
                    'dataset_version_id' => $rainfallVersion->id,
                    'schema_key' => 'long_admin_period_indicator',
                    'status' => 'completed_with_errors',
                ],
                [
                    'scanned_rows' => $rainfallVersion->row_count + 1,
                    'accepted_rows' => $rainfallVersion->row_count,
                    'rejected_rows' => 1,
                    'warning_count' => 0,
                    'error_count' => 1,
                    'started_at' => now()->subMinute(),
                    'completed_at' => now(),
                    'summary' => ['sample' => true],
                ]
            );

            ImportError::updateOrCreate(
                [
                    'import_id' => $errorImport->id,
                    'row_number' => 3,
                ],
                [
                    'column_name' => 'date',
                    'error_code' => 'row_validation_failed',
                    'message' => 'Date column must be parseable.',
                    'raw_row' => ['date' => 'not-a-date', 'pcode' => 'NG002', 'rfh' => '10.2'],
                ]
            );
        }

        $this->seedCoverageSamples();
        $this->backfillExtendedSampleKeys();
    }

    /** @return array<int, array<string, mixed>> */
    private function sourceDerivedEmissionSample(int $datasetVersionId, ?int $fallbackSourceId): array
    {
        $path = base_path('climate-data/emission-approved/development/cw-historical-emissions-primap-dev-sample.csv');

        if (! is_file($path) || ($handle = fopen($path, 'r')) === false) {
            throw new RuntimeException('The source-derived emissions development sample is unavailable.');
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            throw new RuntimeException('The source-derived emissions development sample has no header row.');
        }

        $records = [];
        $now = now();

        while (($values = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, $values);

            if ($row === false) {
                continue;
            }

            $sourceName = trim((string) ($row['Source'] ?? ''));
            $source = $sourceName === ''
                ? null
                : Source::firstOrCreate(['code' => Str::slug($sourceName, '_')], ['name' => $sourceName]);
            $sectorName = trim((string) $row['sector']);
            $gasName = trim((string) $row['gas']);
            $sector = Sector::firstOrCreate(['code' => Str::slug($sectorName, '_')], ['name' => $sectorName]);
            $gas = Gas::firstOrCreate(['code' => Str::slug($gasName, '_')], ['name' => $gasName]);

            foreach ($row as $column => $value) {
                if (! preg_match('/^\d{4}$/', $column) || $value === null || $value === '') {
                    continue;
                }

                $year = (int) $column;
                $sourceId = $source?->id ?? $fallbackSourceId;
                $records[] = [
                    'dataset_version_id' => $datasetVersionId,
                    'record_key' => $this->countrySectorGasRecordKey($datasetVersionId, $sourceId, $sector->id, $gas->id, $row['country'], $year),
                    'source_id' => $sourceId,
                    'sector_id' => $sector->id,
                    'gas_id' => $gas->id,
                    'country_code' => $row['country'],
                    'year' => $year,
                    'value' => (float) $value,
                    'metadata' => json_encode([
                        'sample' => true,
                        'development_fixture' => true,
                        'source_file' => 'CW_HistoricalEmissions_PRIMAP.csv',
                        'source_series' => $sourceName,
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        fclose($handle);

        return $records;
    }

    private function seedCoverageSamples(): void
    {
        $indicator = Indicator::where('code', 'population')->first();
        $rainfallIndicator = Indicator::where('code', 'rfh')->first();
        $sector = Sector::where('code', 'agriculture')->first();
        $gas = Gas::where('code', 'co2')->first();
        $geography = Geography::where('code', 'NG002')->first();
        $unit = Unit::where('code', 'people')->first();

        Dataset::query()->with('versions')->where('status', 'active')->get()->each(function (Dataset $dataset) use ($indicator, $rainfallIndicator, $sector, $gas, $geography, $unit): void {
            $version = $dataset->versions->firstWhere('version_label', 'sample-v1');

            if (! $version || $this->factCount($dataset->default_fact_table, $version->id) > 0) {
                return;
            }

            $now = now();
            $metadata = json_encode(['sample' => true, 'development_fixture' => true]);

            match ($dataset->default_fact_table) {
                'country_year_sector_gas_values' => CountryYearSectorGasValue::create([
                    'dataset_version_id' => $version->id,
                    'record_key' => $this->countrySectorGasRecordKey($version->id, $dataset->source_id, $sector?->id, $gas?->id, 'NGA', 2024),
                    'source_id' => $dataset->source_id, 'sector_id' => $sector?->id, 'gas_id' => $gas?->id,
                    'country_code' => 'NGA', 'year' => 2024, 'value' => 1.0, 'metadata' => $metadata, 'created_at' => $now, 'updated_at' => $now,
                ]),
                'admin_period_indicator_values' => $geography && $rainfallIndicator ? AdminPeriodIndicatorValue::create($this->adminIndicatorSample($version->id, $dataset->source_id, $geography, $rainfallIndicator->id, '2026-04-11', 'dekad', 10.0)) : null,
                'country_year_indicator_values' => $indicator ? CountryYearIndicatorValue::create([
                    'dataset_version_id' => $version->id,
                    'record_key' => hash('sha256', implode("\x1F", ['country_year_indicator_values', $version->id, $dataset->source_id ?? 0, $indicator->id, 'NGA', 2024])),
                    'source_id' => $dataset->source_id, 'indicator_id' => $indicator->id, 'country_code' => 'NGA', 'year' => 2024,
                    'value' => 1.0, 'metadata' => $metadata, 'created_at' => $now, 'updated_at' => $now,
                ]) : null,
                'country_documents' => CountryDocument::create([
                    'dataset_version_id' => $version->id,
                    'record_key' => hash('sha256', implode("\x1F", ['country_documents', $version->id, 'NGA', "dev-{$dataset->code}"])),
                    'source_id' => $dataset->source_id, 'country_code' => 'NGA', 'document_code' => "dev-{$dataset->code}",
                    'title' => "Development sample: {$dataset->title}", 'status' => 'sample', 'summary' => 'Development fixture; replace with reviewed source data before publication.',
                    'payload' => $metadata, 'created_at' => $now, 'updated_at' => $now,
                ]),
                'country_document_sector_responses' => CountryDocumentSectorResponse::create([
                    'dataset_version_id' => $version->id,
                    'record_key' => hash('sha256', implode("\x1F", ['country_document_sector_responses', $version->id, 'NGA', "dev-{$dataset->code}", 'sample_response', ''])),
                    'source_id' => $dataset->source_id, 'sector_id' => $sector?->id, 'country_code' => 'NGA', 'document_code' => "dev-{$dataset->code}",
                    'question_code' => 'sample_response', 'response_text' => 'Development fixture; replace with reviewed source data before publication.',
                    'metadata' => $metadata, 'created_at' => $now, 'updated_at' => $now,
                ]),
                'event_impacts' => EventImpact::create([
                    'dataset_version_id' => $version->id,
                    'record_key' => hash('sha256', implode("\x1F", ['event_impacts', $version->id, 'NGA', $geography?->code ?? '', '2026-04-12', 'flood', "Development sample: {$dataset->code}", 'people_displaced'])),
                    'source_id' => $dataset->source_id, 'geography_id' => $geography?->id, 'unit_id' => $unit?->id, 'country_code' => 'NGA', 'geography_code' => $geography?->code,
                    'event_date' => '2026-04-12', 'event_type' => 'flood', 'event_name' => "Development sample: {$dataset->code}", 'metric_code' => 'people_displaced',
                    'value' => 1, 'metadata' => $metadata, 'created_at' => $now, 'updated_at' => $now,
                ]),
                default => null,
            };

            $version->update(['row_count' => $this->factCount($dataset->default_fact_table, $version->id)]);
        });
    }

    private function factCount(?string $table, int $versionId): int
    {
        return $table ? DB::table($table)->where('dataset_version_id', $versionId)->count() : 0;
    }

    private function backfillExtendedSampleKeys(): void
    {
        foreach ([
            'country_year_indicator_values' => fn (object $row): array => ['country_year_indicator_values', $row->dataset_version_id, $row->source_id ?? 0, $row->indicator_id, $row->country_code, $row->year],
            'country_documents' => fn (object $row): array => ['country_documents', $row->dataset_version_id, $row->country_code, $row->document_code],
            'country_document_sector_responses' => fn (object $row): array => ['country_document_sector_responses', $row->dataset_version_id, $row->country_code, $row->document_code, $row->question_code, $row->subsector_code ?? ''],
            'event_impacts' => fn (object $row): array => ['event_impacts', $row->dataset_version_id, $row->country_code, $row->geography_code ?? '', $row->event_date, $row->event_type, $row->event_name ?? '', $row->metric_code],
        ] as $tableName => $parts) {
            DB::table($tableName)->whereNull('record_key')->orderBy('id')->chunkById(1000, function ($rows) use ($tableName, $parts): void {
                foreach ($rows as $row) {
                    DB::table($tableName)->where('id', $row->id)->update(['record_key' => hash('sha256', implode("\x1F", $parts($row)))]);
                }
            });
        }
    }

    private function adminIndicatorSample(
        int $datasetVersionId,
        ?int $sourceId,
        Geography $geography,
        int $indicatorId,
        string $periodDate,
        string $periodType,
        float $value,
    ): array {
        return [
            'dataset_version_id' => $datasetVersionId,
            'record_key' => hash('sha256', implode("\x1F", [
                'admin_period_indicator_values',
                $datasetVersionId,
                $indicatorId,
                $geography->code,
                $periodDate,
                $periodType,
            ])),
            'source_id' => $sourceId,
            'geography_id' => $geography->id,
            'indicator_id' => $indicatorId,
            'geography_code' => $geography->code,
            'period_date' => $periodDate,
            'period_type' => $periodType,
            'value' => $value,
            'metadata' => json_encode(['sample' => true, 'development_fixture' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function countrySectorGasRecordKey(int $datasetVersionId, ?int $sourceId, ?int $sectorId, ?int $gasId, string $countryCode, int $year): string
    {
        return hash('sha256', implode("\x1F", [
            'country_year_sector_gas_values',
            $datasetVersionId,
            $sourceId ?? 0,
            $sectorId ?? 0,
            $gasId ?? 0,
            $countryCode,
            $year,
        ]));
    }
}

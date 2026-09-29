<?php

namespace App\Console\Commands;

use App\Models\AdminPeriodIndicatorValue;
use App\Models\Dataset;
use App\Models\Geography;
use App\Models\Indicator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LoadRainfallDevelopmentFixture extends Command
{
    protected $signature = 'climatehub:load-rainfall-development-fixture
        {--source= : Absolute or project-relative reference CSV path}
        {--full-history : Load the complete state-level history while retaining the compact LGA sample}';

    protected $description = 'Load a diverse, source-derived Nigerian state and LGA rainfall fixture for local development.';

    private const PERIODS = [
        '2024-04-11',
        '2024-10-11',
        '2025-04-11',
        '2025-10-11',
        '2026-01-11',
        '2026-04-11',
    ];

    private const INDICATORS = ['rfh', 'rfh_avg', 'r1h', 'r1h_avg', 'r3h', 'r3h_avg', 'rfq', 'r1q', 'r3q'];

    public function handle(): int
    {
        $sourcePath = $this->option('source') ?: base_path('climate-data/nga-rainfall-subnat-full.csv');

        if (! is_file($sourcePath)) {
            $this->components->error("Reference CSV was not found: {$sourcePath}");

            return self::FAILURE;
        }

        $dataset = Dataset::query()->where('code', 'nigeria_rainfall_subnational')->first();
        $version = $dataset?->versions()->where('version_label', 'sample-v1')->first();

        if (! $dataset || ! $version) {
            $this->components->error('The Nigerian rainfall dataset sample version is not available. Run the normal database seeder first.');

            return self::FAILURE;
        }

        $geographies = Geography::query()->whereIn('level', [1, 2])->get()->keyBy('code');
        $indicators = Indicator::query()->whereIn('code', self::INDICATORS)->get()->keyBy('code');
        $handle = fopen($sourcePath, 'r');
        $headers = $handle ? fgetcsv($handle) : false;

        if (! $handle || ! $headers) {
            $this->components->error('The reference CSV could not be read.');

            return self::FAILURE;
        }

        $positions = array_flip($headers);
        $rows = [];
        $rowCount = 0;
        $timestamp = now();

        DB::beginTransaction();

        try {
            AdminPeriodIndicatorValue::query()->where('dataset_version_id', $version->id)->delete();

            while (($line = fgetcsv($handle)) !== false) {
                $date = $line[$positions['date']] ?? null;
                $pcode = $line[$positions['PCODE']] ?? null;

                $admLevel = $line[$positions['adm_level']] ?? null;

                if (! in_array($admLevel, ['1', '2'], true) || ! $geographies->has($pcode)) {
                    continue;
                }

                // The homepage needs the full national history; the LGA fixture stays compact for local development.
                if (! $this->option('full-history') && ! in_array($date, self::PERIODS, true)) {
                    continue;
                }

                if ($this->option('full-history') && $admLevel === '2' && ! in_array($date, self::PERIODS, true)) {
                    continue;
                }

                $geography = $geographies->get($pcode);
                foreach (self::INDICATORS as $indicatorCode) {
                    $value = $line[$positions[$indicatorCode]] ?? null;
                    $indicator = $indicators->get($indicatorCode);

                    if ($value === null || $value === '' || ! $indicator) {
                        continue;
                    }

                    $rows[] = [
                        'dataset_version_id' => $version->id,
                        'record_key' => hash('sha256', implode("\x1F", ['admin_period_indicator_values', $version->id, $indicator->id, $pcode, $date, 'dekad'])),
                        'source_id' => $dataset->source_id,
                        'geography_id' => $geography->id,
                        'indicator_id' => $indicator->id,
                        'geography_code' => $pcode,
                        'period_date' => $date,
                        'period_type' => 'dekad',
                        'value' => (float) $value,
                        'metadata' => json_encode([
                            'development_fixture' => true,
                            'source_file' => 'climate-data/nga-rainfall-subnat-full.csv',
                            'source_status' => $line[$positions['version']] ?? null,
                            'n_pixels' => isset($line[$positions['n_pixels']]) ? (float) $line[$positions['n_pixels']] : null,
                        ]),
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];

                    if (count($rows) === 1000) {
                        AdminPeriodIndicatorValue::query()->upsert($rows, ['record_key'], [
                            'source_id', 'geography_id', 'indicator_id', 'geography_code', 'period_date', 'period_type', 'value', 'metadata', 'updated_at',
                        ]);
                        $rowCount += count($rows);
                        $rows = [];
                    }
                }
            }

            if ($rows !== []) {
                AdminPeriodIndicatorValue::query()->upsert($rows, ['record_key'], [
                    'source_id', 'geography_id', 'indicator_id', 'geography_code', 'period_date', 'period_type', 'value', 'metadata', 'updated_at',
                ]);
                $rowCount += count($rows);
            }

            $rowCount = AdminPeriodIndicatorValue::query()
                ->where('dataset_version_id', $version->id)
                ->count();
            $version->update(['row_count' => $rowCount]);
            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        } finally {
            fclose($handle);
        }

        $this->components->info('Loaded '.$rowCount.' state and LGA rainfall values from the local reference CSV.');

        return self::SUCCESS;
    }
}

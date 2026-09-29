<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use App\Models\Gas;
use App\Models\Geography;
use App\Models\GeographyAlias;
use App\Models\GeographyShape;
use App\Models\Indicator;
use App\Models\Sector;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['code' => 'mtco2e', 'name' => 'Million tonnes CO2e', 'symbol' => 'MtCO2e'],
            ['code' => 'mtco2', 'name' => 'Million tonnes CO2', 'symbol' => 'MtCO2'],
            ['code' => 'kt', 'name' => 'Kilotonnes', 'symbol' => 'kt'],
            ['code' => 'mm', 'name' => 'Millimeters', 'symbol' => 'mm'],
            ['code' => 'percent', 'name' => 'Percent', 'symbol' => '%'],
            ['code' => 'count', 'name' => 'Count', 'symbol' => null],
            ['code' => 'usd_constant_2015', 'name' => 'Constant 2015 US dollars', 'symbol' => 'USD'],
            ['code' => 'people', 'name' => 'People', 'symbol' => null],
            ['code' => 'index', 'name' => 'Index', 'symbol' => null],
        ];

        foreach ($units as $unit) {
            Unit::updateOrCreate(['code' => $unit['code']], $unit);
        }

        $sectors = [
            'adaptation',
            'agriculture',
            'buildings',
            'bunker-fuels',
            'climate-finance',
            'electricity-heat',
            'energy',
            'fluorinated-gases',
            'forestry-and-land-use',
            'fossil-fuel-operations',
            'industry',
            'land-use',
            'manufacturing',
            'power',
            'resilience',
            'transport',
            'waste',
            'water',
        ];

        foreach ($sectors as $sector) {
            Sector::updateOrCreate(
                ['code' => $sector],
                ['name' => str($sector)->headline()->toString()]
            );
        }

        $gases = [
            ['code' => 'all_ghg', 'name' => 'All GHG'],
            ['code' => 'co2', 'name' => 'CO2'],
            ['code' => 'ch4', 'name' => 'CH4'],
            ['code' => 'n2o', 'name' => 'N2O'],
            ['code' => 'co2e_20yr', 'name' => 'CO2e 20yr'],
            ['code' => 'pm2_5', 'name' => 'PM2.5'],
            ['code' => 'black_carbon', 'name' => 'Black carbon'],
        ];

        foreach ($gases as $gas) {
            Gas::updateOrCreate(['code' => $gas['code']], $gas);
        }

        $mm = Unit::where('code', 'mm')->first();
        $percent = Unit::where('code', 'percent')->first();
        $count = Unit::where('code', 'count')->first();
        $people = Unit::where('code', 'people')->first();
        $usd = Unit::where('code', 'usd_constant_2015')->first();
        $index = Unit::where('code', 'index')->first();

        $indicators = [
            ['code' => 'rfh', 'name' => '10 Day Rainfall', 'unit_id' => $mm?->id, 'grain' => 'admin_period_indicator'],
            ['code' => 'rfh_avg', 'name' => '10 Day Rainfall Average', 'unit_id' => $mm?->id, 'grain' => 'admin_period_indicator'],
            ['code' => 'r1h', 'name' => '1 Month Rainfall', 'unit_id' => $mm?->id, 'grain' => 'admin_period_indicator'],
            ['code' => 'r1h_avg', 'name' => '1 Month Rainfall Average', 'unit_id' => $mm?->id, 'grain' => 'admin_period_indicator'],
            ['code' => 'r3h', 'name' => '3 Month Rainfall', 'unit_id' => $mm?->id, 'grain' => 'admin_period_indicator'],
            ['code' => 'r3h_avg', 'name' => '3 Month Rainfall Average', 'unit_id' => $mm?->id, 'grain' => 'admin_period_indicator'],
            ['code' => 'rfq', 'name' => '10 Day Rainfall Anomaly', 'unit_id' => $percent?->id, 'grain' => 'admin_period_indicator'],
            ['code' => 'r1q', 'name' => '1 Month Rainfall Anomaly', 'unit_id' => $percent?->id, 'grain' => 'admin_period_indicator'],
            ['code' => 'r3q', 'name' => '3 Month Rainfall Anomaly', 'unit_id' => $percent?->id, 'grain' => 'admin_period_indicator'],
            ['code' => 'ag_land_pct', 'name' => 'Agricultural Land Share', 'unit_id' => $percent?->id, 'grain' => 'country_year_indicator'],
            ['code' => 'water_withdrawal_pc', 'name' => 'Water Withdrawal Per Capita', 'unit_id' => $count?->id ?? null, 'grain' => 'country_year_indicator'],
            ['code' => 'population', 'name' => 'Population', 'unit_id' => $people?->id, 'grain' => 'country_year_indicator'],
            ['code' => 'gdp_constant_2015_usd', 'name' => 'GDP Constant 2015 USD', 'unit_id' => $usd?->id, 'grain' => 'country_year_indicator'],
            ['code' => 'adaptation_vulnerability', 'name' => 'Adaptation Vulnerability', 'unit_id' => $index?->id, 'grain' => 'country_year_indicator'],
            ['code' => 'adaptation_readiness', 'name' => 'Adaptation Readiness', 'unit_id' => $index?->id, 'grain' => 'country_year_indicator'],
            ['code' => 'flood_exposure_score', 'name' => 'Flood Exposure Score', 'unit_id' => $index?->id, 'grain' => 'admin_period_indicator'],
            ['code' => 'risk_vulnerability_score', 'name' => 'Risk Vulnerability Score', 'unit_id' => $index?->id, 'grain' => 'admin_period_indicator'],
            ['code' => 'gcf_project_count', 'name' => 'GCF Project Count', 'unit_id' => $count?->id, 'grain' => 'country_year_indicator'],
        ];

        foreach ($indicators as $indicator) {
            Indicator::updateOrCreate(['code' => $indicator['code']], $indicator);
        }

        $geographies = [
            [
                'code' => 'NGA',
                'source_pcode' => 'NGA',
                'name' => 'Nigeria',
                'country_code' => 'NGA',
                'level' => 0,
                'type' => 'country',
                'boundary_version' => 'sample-boundary-v1',
            ],
            [
                'code' => 'NG002',
                'source_pcode' => 'NG002',
                'name' => 'Adamawa',
                'country_code' => 'NGA',
                'level' => 1,
                'type' => 'administrative',
                'boundary_version' => 'sample-boundary-v1',
            ],
            [
                'code' => 'NG025',
                'source_pcode' => 'NG025',
                'name' => 'Lagos',
                'country_code' => 'NGA',
                'level' => 1,
                'type' => 'administrative',
                'boundary_version' => 'sample-boundary-v1',
            ],
            [
                'code' => 'NG001',
                'source_pcode' => 'NG001',
                'name' => 'Abia',
                'country_code' => 'NGA',
                'level' => 1,
                'type' => 'administrative',
                'boundary_version' => 'sample-boundary-v1',
            ],
            [
                'code' => 'NG003',
                'source_pcode' => 'NG003',
                'name' => 'Akwa Ibom',
                'country_code' => 'NGA',
                'level' => 1,
                'type' => 'administrative',
                'boundary_version' => 'sample-boundary-v1',
            ],
            [
                'code' => 'NG005',
                'source_pcode' => 'NG005',
                'name' => 'Bauchi',
                'country_code' => 'NGA',
                'level' => 1,
                'type' => 'administrative',
                'boundary_version' => 'sample-boundary-v1',
            ],
        ];

        foreach ($geographies as $geography) {
            $record = Geography::firstOrCreate(['code' => $geography['code']], $geography);

            GeographyAlias::updateOrCreate(
                [
                    'source_name' => 'sample',
                    'alias_type' => 'pcode',
                    'normalized_alias' => str($record->code)->lower()->toString(),
                ],
                [
                    'geography_id' => $record->id,
                    'alias_value' => $record->code,
                    'metadata' => ['sample' => true],
                ]
            );

            GeographyShape::updateOrCreate(
                [
                    'geography_id' => $record->id,
                    'boundary_version' => 'sample-boundary-v1',
                    'simplification' => 'overview',
                ],
                [
                    'geography_level' => $record->level,
                    'geometry_status' => 'sample',
                    'source_path' => 'seeded-reference-data',
                    'source_layer' => 'sample',
                    'source_feature_id' => $record->code,
                    'geometry_geojson' => null,
                    'centroid_latitude' => null,
                    'centroid_longitude' => null,
                    'bbox' => null,
                    'area_sq_km' => null,
                    'metadata' => ['sample' => true, 'full_geometry_loaded' => false],
                ]
            );
        }

        $documentTypes = [
            ['code' => 'ndc', 'name' => 'Nationally Determined Contribution'],
            ['code' => 'nap', 'name' => 'National Adaptation Plan'],
            ['code' => 'lts', 'name' => 'Long-Term Strategy'],
        ];

        foreach ($documentTypes as $documentType) {
            DocumentType::updateOrCreate(['code' => $documentType['code']], $documentType);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            [
                'code' => 'climate_watch',
                'name' => 'Climate Watch',
                'organization_name' => 'World Resources Institute',
                'website_url' => 'https://www.climatewatchdata.org/',
                'license_name' => 'CC BY 4.0',
                'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
                'citation_template' => 'Climate Watch, World Resources Institute. Dataset accessed through ClimateHub; cite the specific dataset, source, and version shown on the record.',
                'metadata' => [
                    'editorial_note' => 'Use Climate Watch records as source-specific estimates. Keep country, sector, gas, unit, year range, and version visible, and do not silently merge series from other emissions providers.',
                ],
            ],
            [
                'code' => 'fao',
                'name' => 'FAO',
                'organization_name' => 'Food and Agriculture Organization of the United Nations',
                'website_url' => 'https://www.fao.org/',
                'citation_template' => 'Food and Agriculture Organization of the United Nations. Dataset accessed through ClimateHub; cite the source table, indicator, period, and version shown on the record.',
                'metadata' => [
                    'editorial_note' => 'Use FAO records for agriculture, food-system, land, water, and event context only where the indicator definition is visible to the reader.',
                ],
            ],
            [
                'code' => 'wfp',
                'name' => 'WFP',
                'organization_name' => 'World Food Programme',
                'website_url' => 'https://www.wfp.org/',
                'citation_template' => 'World Food Programme. Dataset accessed through ClimateHub; cite the geography, rainfall indicator, dekad, source file, and version shown on the record.',
                'metadata' => [
                    'editorial_note' => 'Rainfall indicators should be explained with their aggregation window, anomaly basis, geography level, and data status before interpretation.',
                ],
            ],
            [
                'code' => 'climate_trace',
                'name' => 'Climate Trace',
                'organization_name' => 'Climate Trace',
                'website_url' => 'https://climatetrace.org/',
                'citation_template' => 'Climate TRACE. Dataset accessed through ClimateHub; cite the source, sector, gas or pollutant, period, and version shown on the record.',
                'metadata' => [
                    'editorial_note' => 'Use Climate TRACE records as provider-specific estimates and show source distinctions when comparing with national or Climate Watch emissions series.',
                ],
            ],
            [
                'code' => 'climate_resource',
                'name' => 'Climate Resource',
                'organization_name' => 'Climate Resource',
                'website_url' => 'https://climate-resource.com/',
                'license_name' => 'CC BY-NC 4.0',
                'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
                'citation_template' => 'Gutschow et al. PRIMAP-hist national historical emissions time series. Dataset accessed through ClimateHub; cite the source version shown on the record.',
                'metadata' => [
                    'editorial_note' => 'Use PRIMAP as a separate historical emissions source. Do not combine with Climate Watch, UNFCCC, or Global Carbon Project series without a visible source comparison.',
                ],
            ],
            [
                'code' => 'unfccc',
                'name' => 'UNFCCC',
                'organization_name' => 'United Nations Framework Convention on Climate Change',
                'website_url' => 'https://unfccc.int/',
                'citation_template' => 'UNFCCC greenhouse gas inventory data. Dataset accessed through ClimateHub; cite the reported party, inventory year, and version shown on the record.',
                'metadata' => [
                    'editorial_note' => 'Use UNFCCC records as reported inventory data and preserve reporting-status caveats from the source metadata.',
                ],
            ],
            [
                'code' => 'global_carbon_project',
                'name' => 'Global Carbon Project',
                'organization_name' => 'Global Carbon Project',
                'website_url' => 'https://globalcarbonbudget.org/',
                'citation_template' => 'Global Carbon Project. Dataset accessed through ClimateHub; cite the Global Carbon Budget release shown on the record.',
                'metadata' => [
                    'editorial_note' => 'Use Global Carbon Project records only as a separate territorial CO2 emissions source unless a methodology note approves another comparison.',
                ],
            ],
            [
                'code' => 'world_bank',
                'name' => 'World Bank',
                'organization_name' => 'World Bank',
                'website_url' => 'https://data.worldbank.org/',
                'citation_template' => 'World Bank. Dataset accessed through ClimateHub; cite the indicator, country, year, and version shown on the record.',
                'metadata' => [
                    'editorial_note' => 'Use World Bank records for context indicators and denominators, not as a climate-impact measure by itself.',
                ],
            ],
            [
                'code' => 'nema',
                'name' => 'NEMA',
                'organization_name' => 'National Emergency Management Agency',
                'citation_template' => 'National Emergency Management Agency records as represented in the local ClimateHub source workbook; cite the event, geography, source sheet, and version shown on the record.',
                'metadata' => [
                    'country_code' => 'NGA',
                    'editorial_note' => 'Named from local Nigeria flood workbook tabs; verify publication details before treating the source as externally confirmed.',
                ],
            ],
            [
                'code' => 'dataphyte',
                'name' => 'Dataphyte',
                'organization_name' => 'Dataphyte Foundation',
                'website_url' => 'https://dataphyte.com/',
                'citation_template' => 'Dataphyte Foundation. Dataset accessed through ClimateHub; cite the dataset, source document, extraction date where available, and ClimateHub version shown on the record.',
                'metadata' => [
                    'editorial_note' => 'Use Dataphyte records for curated Nigeria climate policy, document, finance, and local context where the underlying source document remains linked.',
                ],
            ],
        ];

        foreach ($sources as $source) {
            Source::updateOrCreate(
                ['code' => $source['code']],
                $source
            );
        }
    }
}

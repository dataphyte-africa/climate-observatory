<?php

namespace Database\Seeders;

use App\Models\Dataset;
use App\Models\DatasetVersion;
use App\Models\Source;
use Illuminate\Database\Seeder;

class DatasetCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $climateWatch = Source::where('code', 'climate_watch')->first();
        $fao = Source::where('code', 'fao')->first();
        $wfp = Source::where('code', 'wfp')->first();
        $climateTrace = Source::where('code', 'climate_trace')->first();
        $worldBank = Source::where('code', 'world_bank')->first();
        $nema = Source::where('code', 'nema')->first();
        $dataphyte = Source::where('code', 'dataphyte')->first();

        $datasets = [
            [
                'code' => 'climatewatch_historical_emissions',
                'slug' => 'climatewatch-historical-emissions',
                'title' => 'Climate Watch Historical Emissions',
                'dataset_type' => 'country_year_sector_gas',
                'import_schema' => 'wide_country_sector_gas_year',
                'default_fact_table' => 'country_year_sector_gas_values',
                'source_id' => $climateWatch?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'Country-year greenhouse gas emissions by sector, gas, source, and reporting year.',
                'description' => 'Use this dataset to compare historical greenhouse-gas emissions over time while keeping provider, sector, gas, unit, and year range visible. ClimateHub treats each provider series as its own estimate and does not merge Climate Watch, GCP, PRIMAP, UNFCCC, or other source series without showing the source distinction.',
                'settings' => [
                    'methodology_code' => 'emissions_source_comparison',
                    'coverage_note' => 'Country-year records by source, sector, gas, and year. Public views must show the active source series and unit beside each chart or table.',
                    'caveats' => [
                        'Different providers can use different accounting methods, sector mappings, gases, and revision cycles.',
                        'Do not compare source totals as if they were one harmonized time series unless the method explicitly says so.',
                    ],
                    'glossary' => [
                        'all_ghg' => 'All greenhouse gases included by the selected source series.',
                        'co2e_20yr' => 'Carbon dioxide equivalent calculated with a 20-year warming basis where available.',
                    ],
                ],
            ],
            [
                'code' => 'nigeria_rainfall_subnational',
                'slug' => 'nigeria-rainfall-subnational',
                'title' => 'Nigeria Rainfall Indicators at Subnational Level',
                'dataset_type' => 'admin_period_indicator',
                'import_schema' => 'long_admin_period_indicator',
                'default_fact_table' => 'admin_period_indicator_values',
                'source_id' => $wfp?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'Dekadal rainfall totals and anomaly indicators by Nigerian administrative geography.',
                'description' => 'Use this dataset to inspect recent rainfall conditions across Nigerian states and LGAs. Values are keyed by geography, period, indicator, and version so readers can separate observed, preliminary, forecast, and anomaly-style rainfall indicators.',
                'settings' => [
                    'methodology_code' => 'subnational_rainfall_indicators',
                    'coverage_note' => 'Subnational rainfall records use canonical geography codes and dekadal or rolling-period indicators.',
                    'caveats' => [
                        'Missing or unavailable geography-period combinations must be shown as missing, not as zero rainfall.',
                        'Forecast, preliminary, and final values should remain labelled wherever source metadata provides that status.',
                    ],
                    'glossary' => [
                        'rfh' => 'Rainfall total for the selected 10-day dekad.',
                        'r1h' => 'Rainfall total for the selected one-month period.',
                        'r3h' => 'Rainfall total for the selected three-month period.',
                        'rfq' => 'Rainfall anomaly for the selected 10-day dekad.',
                        'r1q' => 'Rainfall anomaly for the selected one-month period.',
                        'r3q' => 'Rainfall anomaly for the selected three-month period.',
                    ],
                ],
            ],
            [
                'code' => 'nigeria_country_year_indicators',
                'slug' => 'nigeria-country-year-indicators',
                'title' => 'Nigeria Country-Year Indicators',
                'dataset_type' => 'country_year_indicator',
                'import_schema' => null,
                'default_fact_table' => 'country_year_indicator_values',
                'source_id' => $fao?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'Annual Nigeria indicators used for agriculture, water, environment, and socioeconomic context.',
                'description' => 'Use this dataset for country-level context around agriculture, land, water, environment, and socioeconomic indicators. Each indicator must retain its unit, source, year, and definition so unlike measures are not presented on one scale.',
                'settings' => [
                    'methodology_code' => 'country_year_indicator_dictionary',
                    'coverage_note' => 'Country-year indicator records are organized by country, indicator, source, year, and unit.',
                    'caveats' => [
                        'Indicators with different units should be described separately or shown as small multiples.',
                        'Do not create composite scores from unrelated indicators without an approved methodology.',
                    ],
                    'glossary' => [
                        'ag_land_pct' => 'Share of land area classified as agricultural land.',
                        'water_withdrawal_pc' => 'Water withdrawal measure presented per person where the source provides the denominator.',
                    ],
                ],
            ],
            [
                'code' => 'nigeria_climate_policy_documents',
                'slug' => 'nigeria-climate-policy-documents',
                'title' => 'Nigeria Climate Policy Documents',
                'dataset_type' => 'country_document',
                'import_schema' => null,
                'default_fact_table' => 'country_documents',
                'source_id' => $dataphyte?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'Nigeria climate policy document records with document type, status, submission date, and source context.',
                'description' => 'Use this dataset to find climate policy documents and keep document versions visible. Public copy should preserve the title, document type, submission date, status, target context, and source wording where available.',
                'settings' => [
                    'methodology_code' => 'policy_document_evidence',
                    'coverage_note' => 'Document records are keyed by country, document code, document type, submission date, and status.',
                    'caveats' => [
                        'Repeated document versions should remain visible instead of being silently deduplicated.',
                        'Document summaries should not overstate implementation status unless the source record says so.',
                    ],
                    'glossary' => [
                        'ndc' => 'Nationally Determined Contribution.',
                        'nap' => 'National Adaptation Plan.',
                        'lts' => 'Long-Term Strategy.',
                    ],
                ],
            ],
            [
                'code' => 'nigeria_document_sector_responses',
                'slug' => 'nigeria-document-sector-responses',
                'title' => 'Nigeria Document Sector Responses',
                'dataset_type' => 'document_sector_response',
                'import_schema' => null,
                'default_fact_table' => 'country_document_sector_responses',
                'source_id' => $dataphyte?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'Sector-level response text extracted from climate policy documents.',
                'description' => 'Use this dataset to search how climate policy documents describe targets, actions, sectors, subsectors, and response fields. Response text should stay close to the source wording and remain linked to the document version it came from.',
                'settings' => [
                    'methodology_code' => 'policy_document_evidence',
                    'coverage_note' => 'Response records are keyed by country, document, sector, question, subsector, and source text.',
                    'caveats' => [
                        'Do not treat the presence of response text as proof of implementation.',
                        'Keep sector and document-version filters visible when summarizing policy coverage.',
                    ],
                ],
            ],
            [
                'code' => 'nigeria_flood_event_impacts',
                'slug' => 'nigeria-flood-event-impacts',
                'title' => 'Nigeria Flood Event Impacts',
                'dataset_type' => 'event_impact',
                'import_schema' => null,
                'default_fact_table' => 'event_impacts',
                'source_id' => $nema?->id ?? $dataphyte?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'Flood event impact records for Nigeria, including affected places, dates, metrics, and source-specific measures.',
                'description' => 'Use this dataset to inspect reported flood events and associated impact measures. Event copy must distinguish reported counts from unavailable measures and should use "Not reported" when a source has no value for a metric.',
                'settings' => [
                    'methodology_code' => 'flood_event_impact_records',
                    'source_files' => [
                        'Nigeria Flood data_.xlsx',
                        'FAO EVE Global Flood Monitoring System_.xlsx',
                    ],
                    'coverage_note' => 'Event records are keyed by country, geography, event date, event type, metric, unit, and source metadata.',
                    'caveats' => [
                        'An absent impact value is not a zero.',
                        'Rainfall and risk data may provide context but should not be merged into the event fact unless a source record links them.',
                    ],
                    'glossary' => [
                        'people_displaced' => 'People reported as displaced by the event.',
                        'affected_people' => 'People reported as affected by the event.',
                    ],
                ],
            ],
            [
                'code' => 'climatewatch_agriculture_profile',
                'slug' => 'climatewatch-agriculture-profile',
                'title' => 'Climate Watch Agriculture Profile',
                'dataset_type' => 'country_year_indicator',
                'import_schema' => null,
                'default_fact_table' => 'country_year_indicator_values',
                'source_id' => $climateWatch?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'Agriculture profile indicators covering area, emissions, employment, meat consumption, pesticides, fertilizers, production, trade, value added, and water.',
                'description' => 'Use this dataset family as an indicator dictionary and small-multiple source for agriculture and environment pages. The source files are wide year-column tables and must retain indicator definitions, categories, subcategories, and units from the legend.',
                'settings' => [
                    'methodology_code' => 'country_year_indicator_dictionary',
                    'source_files' => [
                        'ClimateWatch_AgricultureProfile/CW_Agriculture_area.csv',
                        'ClimateWatch_AgricultureProfile/CW_Agriculture_emissions.csv',
                        'ClimateWatch_AgricultureProfile/CW_Agriculture_legend.csv',
                        'ClimateWatch_AgricultureProfile/CW_Agriculture_production_trade.csv',
                        'ClimateWatch_AgricultureProfile/CW_Agriculture_water.csv',
                    ],
                    'coverage_note' => 'Country-year agriculture indicators, mostly 1990 onward, with emissions extending earlier in the local extract.',
                    'caveats' => [
                        'Keep unlike units in separate charts or tables.',
                        'Use the legend file as the indicator vocabulary source before adding new indicator rows.',
                    ],
                ],
            ],
            [
                'code' => 'climatewatch_adaptation_profile',
                'slug' => 'climatewatch-adaptation-profile',
                'title' => 'Climate Watch Adaptation Profile',
                'dataset_type' => 'country_year_indicator',
                'import_schema' => null,
                'default_fact_table' => 'country_year_indicator_values',
                'source_id' => $climateWatch?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'Country adaptation profile indicators including climate risks, vulnerability, readiness, hazards, and adaptation planning fields.',
                'description' => 'Use this dataset family for country comparison cards and adaptation context. The local CSV is a country-level profile table rather than a year-expanded fact table, so import work should map each numeric profile field into a controlled indicator.',
                'settings' => [
                    'methodology_code' => 'country_year_indicator_dictionary',
                    'source_files' => [
                        'ClimateWatch_Adaptation/CW_adaptation.csv',
                        'ClimateWatch_Adaptation/CW_adaptation_metadata.csv',
                    ],
                    'coverage_note' => '197 country profile rows in the local CSV profile extract.',
                    'caveats' => [
                        'Ranks and scores should not be displayed without plain-language definitions.',
                        'Hazard fields are profile attributes and should not be treated as event facts.',
                    ],
                ],
            ],
            [
                'code' => 'climatewatch_socioeconomics',
                'slug' => 'climatewatch-socioeconomics',
                'title' => 'Climate Watch Socioeconomics',
                'dataset_type' => 'country_year_indicator',
                'import_schema' => null,
                'default_fact_table' => 'country_year_indicator_values',
                'source_id' => $worldBank?->id ?? $climateWatch?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'GDP and population context indicators used as denominators and country profile context.',
                'description' => 'Use this dataset family for contextual charts, per-capita denominators, and country profile summaries. It should be labelled as context data and not presented as a climate impact measure.',
                'settings' => [
                    'methodology_code' => 'country_year_indicator_dictionary',
                    'source_files' => [
                        'ClimateWatch_SocioEconomics/CW_gdp.csv',
                        'ClimateWatch_SocioEconomics/CW_population.csv',
                    ],
                    'coverage_note' => 'Country-year wide tables: GDP from 1960 onward and population from 1800 onward in the local extracts.',
                    'caveats' => [
                        'Use GDP and population as context or denominators only where metric definitions require them.',
                    ],
                ],
            ],
            [
                'code' => 'climatewatch_lts_documents',
                'slug' => 'climatewatch-lts-documents',
                'title' => 'Climate Watch Long-Term Strategies',
                'dataset_type' => 'country_document',
                'import_schema' => null,
                'default_fact_table' => 'country_documents',
                'source_id' => $climateWatch?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'Country long-term strategy submission and target metadata.',
                'description' => 'Use this dataset family for policy discovery and document profiles. Preserve submission status, document links, target labels, and dates where the source provides them.',
                'settings' => [
                    'methodology_code' => 'policy_document_evidence',
                    'source_files' => ['ClimateWatch_LTS/CW_LTS_data.csv'],
                    'coverage_note' => '81 country rows in the local CSV extract.',
                    'caveats' => [
                        'Target labels are document metadata and should not be rewritten as implementation evidence.',
                    ],
                ],
            ],
            [
                'code' => 'climatewatch_ndc_highlevel',
                'slug' => 'climatewatch-ndc-highlevel',
                'title' => 'Climate Watch NDC High-Level Content',
                'dataset_type' => 'country_document',
                'import_schema' => null,
                'default_fact_table' => 'country_documents',
                'source_id' => $climateWatch?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'High-level NDC target, document, mitigation, adaptation, and metadata fields by country and document.',
                'description' => 'Use this dataset family for country and policy document discovery. The local CSV has hundreds of fields, so downstream import work should preserve source column names through payload metadata and expose a curated field set first.',
                'settings' => [
                    'methodology_code' => 'policy_document_evidence',
                    'source_files' => [
                        'ClimateWatch_NDC_Content/CW_NDC_data_highlevel.csv',
                        'ClimateWatch_NDC_Content/CW_NDC_indicator_metadata.csv',
                    ],
                    'coverage_note' => '3,284 local CSV rows with 390 columns.',
                    'caveats' => [
                        'Preserve repeated document versions.',
                        'Use the indicator metadata file before promoting any high-level field into a public filter.',
                    ],
                ],
            ],
            [
                'code' => 'climatewatch_ndc_sector_responses',
                'slug' => 'climatewatch-ndc-sector-responses',
                'title' => 'Climate Watch NDC Sector Responses',
                'dataset_type' => 'document_sector_response',
                'import_schema' => null,
                'default_fact_table' => 'country_document_sector_responses',
                'source_id' => $climateWatch?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'Sector and subsector response text extracted from NDC documents.',
                'description' => 'Use this dataset family for searchable policy matrices and document detail pages. The local raw CSV appears as one comma-delimited header column and should be normalized before importer automation.',
                'settings' => [
                    'methodology_code' => 'policy_document_evidence',
                    'source_files' => [
                        'ClimateWatch_NDC_Content/CW_NDC_data_sector_raw.csv',
                        'ClimateWatch_NDC_Content/CW_NDC_data_sector_user_friendly.xlsx',
                    ],
                    'coverage_note' => '127,668 local raw CSV rows keyed by country, document, sector, subsector, question, and response text after normalization.',
                    'caveats' => [
                        'The raw CSV needs delimiter/header normalization before automated import.',
                        'Presence of response text is not evidence of policy implementation.',
                    ],
                ],
            ],
            [
                'code' => 'climatewatch_ndc_sdg_linkages',
                'slug' => 'climatewatch-ndc-sdg-linkages',
                'title' => 'Climate Watch NDC SDG Linkages',
                'dataset_type' => 'document_sector_response',
                'import_schema' => null,
                'default_fact_table' => 'country_document_sector_responses',
                'source_id' => $climateWatch?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'Links between NDC text, Sustainable Development Goals, targets, sectors, and climate-response categories.',
                'description' => 'Use this dataset family as a cross-reference table between climate document text and SDG classifications. Keep goal, target, sector, status, and text fields visible in detail views.',
                'settings' => [
                    'methodology_code' => 'policy_document_evidence',
                    'source_files' => ['ClimateWatch_NDC_SDG/CW_NDC_SDG_linkages.csv'],
                    'coverage_note' => '24,589 local CSV rows.',
                    'caveats' => [
                        'SDG linkage is a classification, not a measured outcome.',
                    ],
                ],
            ],
            [
                'code' => 'climatewatch_ndc_tracker',
                'slug' => 'climatewatch-ndc-tracker',
                'title' => 'Climate Watch NDC Tracker',
                'dataset_type' => 'country_document',
                'import_schema' => null,
                'default_fact_table' => 'country_documents',
                'source_id' => $climateWatch?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'Country-level NDC status, statements, comparison fields, sources, and dates.',
                'description' => 'Use this dataset family for policy status comparison. Preserve status labels, date fields, and source statements instead of converting them into unsupported conclusions.',
                'settings' => [
                    'methodology_code' => 'policy_document_evidence',
                    'source_files' => ['ClimateWatch_NDC_Tracker/CW_NDC_tracker.csv'],
                    'coverage_note' => '198 local CSV rows with tracker and comparison fields.',
                    'caveats' => [
                        'Tracker status should be displayed as source status, not project judgement.',
                    ],
                ],
            ],
            [
                'code' => 'climatewatch_pledges',
                'slug' => 'climatewatch-pledges',
                'title' => 'Climate Watch Pledges',
                'dataset_type' => 'country_document',
                'import_schema' => null,
                'default_fact_table' => 'country_documents',
                'source_id' => $climateWatch?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'Country pledge summaries, target years, pledge type labels, and emissions baseline fields.',
                'description' => 'Use this dataset family for target and pledge comparison. Keep pledge labels, target year, base year, and source summary fields visible.',
                'settings' => [
                    'methodology_code' => 'policy_document_evidence',
                    'source_files' => ['ClimateWatch_Pledge/CW_pledges_data.csv'],
                    'coverage_note' => '74 local CSV rows.',
                    'caveats' => [
                        'A pledge summary is not the same thing as implemented action.',
                    ],
                ],
            ],
            [
                'code' => 'nigeria_climate_trace_emissions',
                'slug' => 'nigeria-climate-trace-emissions',
                'title' => 'Nigeria Climate TRACE Emissions And Air Pollutants',
                'dataset_type' => 'country_year_sector_gas',
                'import_schema' => null,
                'default_fact_table' => 'country_year_sector_gas_values',
                'source_id' => $climateTrace?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'Nigeria greenhouse gas and air-pollutant estimates by administrative level, city, source, gas, or pollutant.',
                'description' => 'Use this dataset family for Nigeria emissions profiles where Climate TRACE provides subnational, city, source, gas, or pollutant sheets. Keep provider and pollutant distinctions visible.',
                'settings' => [
                    'methodology_code' => 'emissions_source_comparison',
                    'source_files' => ['Nigeria_ Greenhouse Gas and Air Pollutant Emissions_.xlsx'],
                    'coverage_note' => 'Eight local workbook sheets including CO2e 20-year, CH4, PM2.5, city, source, and admin-level extracts.',
                    'caveats' => [
                        'Air-pollutant sheets are not greenhouse-gas totals.',
                        'Do not merge Climate TRACE series with Climate Watch historical emissions without a visible source comparison.',
                    ],
                ],
            ],
            [
                'code' => 'nigeria_risk_assessment_indicators',
                'slug' => 'nigeria-risk-assessment-indicators',
                'title' => 'Nigeria Risk Assessment Indicators',
                'dataset_type' => 'admin_period_indicator',
                'import_schema' => null,
                'default_fact_table' => 'admin_period_indicator_values',
                'source_id' => $dataphyte?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'LGA-level risk assessment indicators for flood exposure, facilities, access, coping, vulnerability, demographics, and rural population.',
                'description' => 'Use this dataset family for LGA choropleths and ranked tables. Each risk dimension needs a definition, denominator, direction, and period before publication.',
                'settings' => [
                    'methodology_code' => 'risk_indicator_dictionary',
                    'source_files' => ['Nigeria - Risk Assessment Indicators.xlsx'],
                    'coverage_note' => 'Eight local workbook sheets including multiple NGA ADM2 indicator families and metadata.',
                    'caveats' => [
                        'Scores and ranks should not be mixed across dimensions without definitions.',
                        'Join to LGA boundaries through canonical PCODE or an approved alias crosswalk.',
                    ],
                ],
            ],
            [
                'code' => 'fao_eve_flood_events',
                'slug' => 'fao-eve-flood-events',
                'title' => 'FAO EVE Flood Events',
                'dataset_type' => 'event_impact',
                'import_schema' => null,
                'default_fact_table' => 'event_impacts',
                'source_id' => $fao?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'FAO EVE flood monitoring records for Nigeria and global flood events.',
                'description' => 'Use this dataset family for event timelines and map context where event date, geography, metric, and source-specific definitions are available.',
                'settings' => [
                    'methodology_code' => 'flood_event_impact_records',
                    'source_files' => ['FAO EVE Global Flood Monitoring System_.xlsx'],
                    'coverage_note' => 'Three local workbook sheets: metadata, Nigeria flood events, and global flood events.',
                    'caveats' => [
                        'Use source-specific event and metric definitions.',
                        'Missing values should display as not reported, not zero.',
                    ],
                ],
            ],
            [
                'code' => 'nigeria_nema_flood_records',
                'slug' => 'nigeria-nema-flood-records',
                'title' => 'Nigeria NEMA Flood Records',
                'dataset_type' => 'event_impact',
                'import_schema' => null,
                'default_fact_table' => 'event_impacts',
                'source_id' => $nema?->id ?? $dataphyte?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'Nigeria flood workbook records covering event, state, LGA, ward, facility, and affected-area sheets.',
                'description' => 'Use this dataset family for Nigeria flood event and impact records after sheet-specific field mapping. Sheet names indicate multiple source shapes, so import should stay sheet-aware.',
                'settings' => [
                    'methodology_code' => 'flood_event_impact_records',
                    'source_files' => ['Nigeria Flood data_.xlsx'],
                    'coverage_note' => 'Ten local workbook sheets covering 2022, 2023, and 2025 flood-related records and metadata.',
                    'caveats' => [
                        'Sheet-level definitions must be preserved.',
                        'Facility records and area records should not be forced into one metric without mapping.',
                    ],
                ],
            ],
            [
                'code' => 'nigeria_ecological_allocations',
                'slug' => 'nigeria-ecological-allocations',
                'title' => 'Nigeria Ecological Allocations',
                'dataset_type' => 'country_year_indicator',
                'import_schema' => null,
                'default_fact_table' => 'country_year_indicator_values',
                'source_id' => $dataphyte?->id,
                'status' => 'active',
                'api_enabled' => true,
                'download_enabled' => true,
                'summary' => 'Ecological allocation workbook records organized by year.',
                'description' => 'Use this dataset family for finance and allocation directory development only after confirming whether each record is an allocation, approval, release, or disbursement.',
                'settings' => [
                    'methodology_code' => 'finance_allocation_records',
                    'source_files' => ['Copy of ECOLOGICAL ALLOCATIONS.xlsx'],
                    'coverage_note' => 'Five local workbook sheets: 2021, 2022, 2023, 2024, and 2025.',
                    'caveats' => [
                        'Do not describe an allocation as a disbursement unless the source field says so.',
                        'Finance records need currency, period, recipient, and transaction-state fields before public totals.',
                    ],
                ],
            ],
        ];

        $featuredDatasetCodes = [
            'climatewatch_historical_emissions',
            'nigeria_rainfall_subnational',
            'nigeria_flood_event_impacts',
            'nigeria_risk_assessment_indicators',
        ];

        $schemasByFactTable = [
            'country_year_sector_gas_values' => 'wide_country_sector_gas_year',
            'admin_period_indicator_values' => 'long_admin_period_indicator',
            'country_year_indicator_values' => 'country_year_indicator',
            'country_documents' => 'country_document',
            'country_document_sector_responses' => 'country_document_sector_response',
            'event_impacts' => 'event_impact',
        ];

        foreach ($datasets as $attributes) {
            $attributes['import_schema'] ??= $schemasByFactTable[$attributes['default_fact_table']] ?? null;
            $attributes['featured'] = in_array($attributes['code'], $featuredDatasetCodes, true);

            $dataset = Dataset::updateOrCreate(
                ['code' => $attributes['code']],
                $attributes
            );

            DatasetVersion::updateOrCreate(
                [
                    'dataset_id' => $dataset->id,
                    'version_label' => 'sample-v1',
                ],
                [
                    'status' => 'published',
                    'row_count' => 0,
                    'activated_at' => now(),
                    'metadata' => ['sample' => true],
                ]
            );
        }
    }
}

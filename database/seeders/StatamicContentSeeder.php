<?php

namespace Database\Seeders;

use App\Models\Dataset;
use App\Models\Source;
use Illuminate\Database\Seeder;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

class StatamicContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureCollections();

        $sourceEntries = [];

        if (Collection::findByHandle('sources')) {
            foreach (Source::query()->get() as $source) {
                $metadata = $source->metadata ?? [];
                $entry = Entry::query()
                    ->where('collection', 'sources')
                    ->where('slug', $source->code)
                    ->first() ?? Entry::make();

                $entry->collection('sources')
                    ->blueprint('source')
                    ->slug($source->code)
                    ->locale('default')
                    ->published(true)
                    ->data([
                        'title' => $source->name,
                        'source_code' => $source->code,
                        'organization_name' => $source->organization_name,
                        'homepage_url' => $source->website_url,
                        'license_summary' => $source->license_name,
                        'license_url' => $source->license_url,
                        'citation_template' => $source->citation_template,
                        'notes' => $metadata['editorial_note'] ?? null,
                    ])
                    ->save();

                $sourceEntries[$source->code] = $entry;
            }
        }

        $methodologyEntries = [];

        if (Collection::findByHandle('methodologies')) {
            $methodologies = [
                [
                    'slug' => 'emissions-source-comparison',
                    'title' => 'Emissions Source Comparison Methodology',
                    'methodology_code' => 'emissions_source_comparison',
                    'summary' => 'Explains how ClimateHub presents country-year emissions by source, sector, gas, unit, and year without blending provider series.',
                    'body' => <<<'MARKDOWN'
ClimateHub treats each emissions provider as a distinct source series. Public views must show the selected source, sector, gas, unit, year range, and version before presenting trends or rankings.

When several providers are available, the comparison should explain that estimates may differ because of accounting boundaries, revision cycles, sector mappings, gas coverage, or warming-potential assumptions. A chart can compare provider series, but it should not imply that they form one harmonized total unless an approved methodology says so.

Users should be able to open the exact table behind a visual, filter by source and gas, and copy a citation that names the dataset and provider.
MARKDOWN,
                    'source_code' => 'climate_watch',
                    'reference_url' => 'https://www.climatewatchdata.org/',
                ],
                [
                    'slug' => 'subnational-rainfall-indicators',
                    'title' => 'Subnational Rainfall Indicator Methodology',
                    'methodology_code' => 'subnational_rainfall_indicators',
                    'summary' => 'Explains dekadal and rolling rainfall indicators by Nigerian geography, including totals, anomalies, versions, and missing values.',
                    'body' => <<<'MARKDOWN'
Rainfall records are organized by geography code, period, indicator, and version. The public interface should translate technical rainfall codes into plain-language labels while preserving the original code for filtering, export, and citation.

Use these labels consistently:

- `rfh`: rainfall total for the selected 10-day dekad.
- `r1h`: rainfall total for the selected one-month period.
- `r3h`: rainfall total for the selected three-month period.
- `rfq`: rainfall anomaly for the selected 10-day dekad.
- `r1q`: rainfall anomaly for the selected one-month period.
- `r3q`: rainfall anomaly for the selected three-month period.

Missing geography-period combinations should be presented as unavailable, not as zero. Where source metadata distinguishes forecast, preliminary, and final values, that status must remain visible beside the map, chart, table, and download.
MARKDOWN,
                    'source_code' => 'wfp',
                    'reference_url' => 'https://www.wfp.org/',
                ],
                [
                    'slug' => 'country-year-indicator-dictionary',
                    'title' => 'Country-Year Indicator Dictionary',
                    'methodology_code' => 'country_year_indicator_dictionary',
                    'summary' => 'Explains annual country indicators, units, denominators, and why unrelated measures should not be combined into one score.',
                    'body' => <<<'MARKDOWN'
Country-year indicators provide context for agriculture, land, water, environment, and socioeconomic analysis. Each indicator should be presented with its source, definition, unit, denominator where available, year, and coverage note.

Indicators with different units should be shown as separate measures or small multiples. ClimateHub should not create a composite score from unlike measures unless a separate approved methodology defines the calculation, weights, denominator, and limitations.
MARKDOWN,
                    'source_code' => 'fao',
                    'reference_url' => 'https://www.fao.org/',
                ],
                [
                    'slug' => 'policy-document-evidence',
                    'title' => 'Policy Document Evidence Methodology',
                    'methodology_code' => 'policy_document_evidence',
                    'summary' => 'Explains how policy document records, versions, sector responses, and source text should be preserved in public copy.',
                    'body' => <<<'MARKDOWN'
Policy records should lead with document discovery: country, document type, document version, submission date, status, sector, and source text. Repeated document versions or repeated response records should stay visible instead of being silently deduplicated.

Sector response text should remain close to the source wording. The presence of a target or response field is evidence that a document contains that language; it is not proof that the action has been implemented unless the source record explicitly says so.
MARKDOWN,
                    'source_code' => 'dataphyte',
                    'reference_url' => 'https://dataphyte.com/',
                ],
                [
                    'slug' => 'flood-event-impact-records',
                    'title' => 'Flood Event Impact Methodology',
                    'methodology_code' => 'flood_event_impact_records',
                    'summary' => 'Explains how reported flood events and impact metrics should distinguish source-specific measures, unavailable values, and contextual datasets.',
                    'body' => <<<'MARKDOWN'
Flood event records should show the event date, geography, event type, source, metric, unit, and value where reported. If a source does not report an impact measure, the public copy should say "Not reported" rather than treating the missing value as zero.

Rainfall and risk records can help users interpret event context, but they should remain linked context unless an approved source record directly joins them to the event fact.
MARKDOWN,
                    'source_code' => 'dataphyte',
                    'reference_url' => 'https://dataphyte.com/',
                ],
                [
                    'slug' => 'csv-import-methodology',
                    'title' => 'CSV Import Methodology',
                    'methodology_code' => 'csv_import_methodology',
                    'summary' => 'Explains how CSV uploads are validated, normalized, and promoted into canonical data tables.',
                    'body' => 'Dataset imports are registered through the Statamic Control Panel, validated in Laravel, and normalized into canonical fact tables. CSV remains the source ingestion format; public content should cite the published dataset version rather than the temporary upload.',
                    'source_code' => 'dataphyte',
                    'reference_url' => 'https://dataphyte.com/',
                ],
                [
                    'slug' => 'risk-indicator-dictionary',
                    'title' => 'Risk Indicator Dictionary',
                    'methodology_code' => 'risk_indicator_dictionary',
                    'summary' => 'Explains how ClimateHub presents LGA-level risk indicators with dimension definitions, denominators, direction, and geography matching notes.',
                    'body' => <<<'MARKDOWN'
Risk indicators should be presented by geography, risk dimension, indicator, source, period, and unit or score definition. A rank or score should not be shown until the public view explains what higher or lower values mean, what denominator is used, and whether the measure is exposure, vulnerability, access, coping capacity, facilities, demographics, or another source dimension.

ClimateHub should join risk records to Nigerian LGA boundaries through canonical PCODEs first. If a source sheet only provides names, the importer or reviewer must record the alias match and expose unmatched geography codes for review.
MARKDOWN,
                    'source_code' => 'dataphyte',
                    'reference_url' => 'https://dataphyte.com/',
                ],
                [
                    'slug' => 'finance-allocation-records',
                    'title' => 'Finance Allocation Records Methodology',
                    'methodology_code' => 'finance_allocation_records',
                    'summary' => 'Explains how ecological allocation records should preserve year, recipient, theme, amount, currency, and transaction-state context.',
                    'body' => <<<'MARKDOWN'
Finance and ecological allocation records should keep the source year, recipient, project or allocation label, theme, amount, currency, and transaction-state wording visible. ClimateHub must not describe an allocation as a disbursement unless the source field explicitly supports that wording.

Public totals should state the covered years, currency basis, missing-value treatment, and whether records are allocations, approvals, releases, disbursements, or another source-defined finance state.
MARKDOWN,
                    'source_code' => 'dataphyte',
                    'reference_url' => 'https://dataphyte.com/',
                ],
            ];

            foreach ($methodologies as $methodology) {
                $entry = Entry::query()
                    ->where('collection', 'methodologies')
                    ->where('slug', $methodology['slug'])
                    ->first() ?? Entry::make();

                $entry->collection('methodologies')
                    ->blueprint('methodology')
                    ->slug($methodology['slug'])
                    ->locale('default')
                    ->published(true)
                    ->data([
                        'title' => $methodology['title'],
                        'methodology_code' => $methodology['methodology_code'],
                        'summary' => $methodology['summary'],
                        'body' => $methodology['body'],
                        'source_entry' => isset($sourceEntries[$methodology['source_code']]) ? [$sourceEntries[$methodology['source_code']]->id()] : [],
                        'reference_url' => $methodology['reference_url'],
                    ])
                    ->save();

                $methodologyEntries[$methodology['methodology_code']] = $entry;
            }
        }

        $datasetEntries = [];

        if (Collection::findByHandle('datasets')) {
            foreach (Dataset::query()->get() as $dataset) {
                $sourceCode = $dataset->source?->code;
                $settings = $dataset->settings ?? [];
                $methodologyCode = $settings['methodology_code'] ?? 'csv_import_methodology';
                $entry = Entry::query()
                    ->where('collection', 'datasets')
                    ->where('slug', $dataset->slug)
                    ->first() ?? Entry::make();

                $entry->collection('datasets')
                    ->blueprint('dataset')
                    ->slug($dataset->slug)
                    ->locale('default')
                    ->published(true)
                    ->data([
                        'title' => $dataset->title,
                        'dataset_code' => $dataset->code,
                        'summary' => $dataset->summary,
                        'description' => $dataset->description,
                        'dataset_type' => $dataset->dataset_type,
                        'update_frequency' => 'annual',
                        'source_entry' => ($sourceCode && isset($sourceEntries[$sourceCode])) ? [$sourceEntries[$sourceCode]->id()] : [],
                        'methodology_entry' => isset($methodologyEntries[$methodologyCode]) ? [$methodologyEntries[$methodologyCode]->id()] : [],
                        'import_schema' => $dataset->import_schema,
                        'default_fact_table' => $dataset->default_fact_table,
                        'api_enabled' => $dataset->api_enabled,
                        'download_enabled' => $dataset->download_enabled,
                        'status' => $dataset->status,
                        'featured' => $dataset->featured,
                        'notes' => $this->datasetNotes($settings),
                    ])
                    ->save();

                $datasetEntries[$dataset->code] = $entry;
            }
        }

        if (Collection::findByHandle('methodologies')) {
            $methodologyDatasets = [
                'emissions_source_comparison' => [
                    'climatewatch_historical_emissions',
                    'nigeria_climate_trace_emissions',
                ],
                'subnational_rainfall_indicators' => ['nigeria_rainfall_subnational'],
                'country_year_indicator_dictionary' => [
                    'nigeria_country_year_indicators',
                    'climatewatch_agriculture_profile',
                    'climatewatch_adaptation_profile',
                    'climatewatch_socioeconomics',
                ],
                'policy_document_evidence' => [
                    'nigeria_climate_policy_documents',
                    'nigeria_document_sector_responses',
                    'climatewatch_lts_documents',
                    'climatewatch_ndc_highlevel',
                    'climatewatch_ndc_sector_responses',
                    'climatewatch_ndc_sdg_linkages',
                    'climatewatch_ndc_tracker',
                    'climatewatch_pledges',
                ],
                'flood_event_impact_records' => [
                    'nigeria_flood_event_impacts',
                    'fao_eve_flood_events',
                    'nigeria_nema_flood_records',
                ],
                'risk_indicator_dictionary' => ['nigeria_risk_assessment_indicators'],
                'finance_allocation_records' => ['nigeria_ecological_allocations'],
            ];

            foreach ($methodologyDatasets as $methodologyCode => $datasetCodes) {
                if (! isset($methodologyEntries[$methodologyCode])) {
                    continue;
                }

                $relatedDatasets = collect($datasetCodes)
                    ->map(fn (string $datasetCode) => $datasetEntries[$datasetCode]->id() ?? null)
                    ->filter()
                    ->values()
                    ->all();

                $methodologyEntries[$methodologyCode]
                    ->set('related_datasets', $relatedDatasets)
                    ->save();
            }
        }
    }

    private function datasetNotes(array $settings): string
    {
        $lines = [];

        if ($coverageNote = $settings['coverage_note'] ?? null) {
            $lines[] = 'Coverage: '.$coverageNote;
        }

        foreach ($settings['caveats'] ?? [] as $caveat) {
            $lines[] = '- '.$caveat;
        }

        if (! empty($settings['glossary'])) {
            $lines[] = 'Glossary labels:';

            foreach ($settings['glossary'] as $code => $label) {
                $lines[] = '- `'.$code.'`: '.$label;
            }
        }

        return implode("\n", $lines);
    }

    private function ensureCollections(): void
    {
        foreach ([
            'sources' => 'Sources',
            'methodologies' => 'Methodologies',
            'datasets' => 'Datasets',
        ] as $handle => $title) {
            if (Collection::findByHandle($handle)) {
                continue;
            }

            Collection::make($handle)
                ->title($title)
                ->routes(false)
                ->save();
        }
    }
}

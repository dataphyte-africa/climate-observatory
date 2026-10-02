<?php

namespace App\Http\Controllers;

use App\Models\AdminPeriodIndicatorValue;
use App\Models\CountryYearSectorGasValue;
use App\Models\Dataset;
use App\Models\Indicator;
use App\Models\Source;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Statamic\Facades\Asset;
use Statamic\Facades\Entry;
use Statamic\Facades\Term;

class DatasetPageController extends Controller
{
    private const THEME_CONFIG = [
        'emissions' => [
            'title' => 'Global emissions',
            'eyebrow' => 'Emissions',
            'summary' => 'Compare reported greenhouse-gas emissions by country, sector, gas, and reporting year.',
            'question' => 'How do reported country emissions compare over time, and which sectors are largest?',
            'dataset_codes' => ['climatewatch_historical_emissions'],
            'primary_visual' => 'Country trends and rankings',
            'state_note' => 'Country emissions are displayed only from the published country-level dataset version.',
            'filters' => ['Metric', 'Year range', 'Sector', 'Gas', 'Source series'],
            'modules' => [
                ['title' => 'National trend', 'type' => 'Chart area', 'status' => 'Development sample values from stored emissions records'],
                ['title' => 'Sector composition', 'type' => 'Latest-year comparison', 'status' => 'Development sample values from stored emissions records'],
                ['title' => 'Exact rows', 'type' => 'Dataset table', 'status' => 'Seeded/API-backed'],
            ],
            'sample_rows' => [
                ['label' => 'All greenhouse gases', 'period' => '1990-2023', 'unit' => 'Source-defined CO2e', 'state' => 'Development sample series'],
                ['label' => 'Sector comparison', 'period' => 'Annual', 'unit' => 'Source-defined emissions unit', 'state' => 'Development sample comparison'],
            ],
        ],
        'rainfall' => [
            'title' => 'Rainfall across Nigeria',
            'eyebrow' => 'Rainfall',
            'summary' => 'Browse rainfall indicators by geography and period, with missing geography-period combinations shown explicitly.',
            'question' => 'Where has rainfall been unusually high or low across administrative areas and time?',
            'dataset_codes' => ['nigeria_rainfall_subnational'],
            'primary_visual' => 'Choropleth map and rainfall table',
            'state_note' => 'Map geometry and boundary-version labels depend on the MAP-01 handoff.',
            'filters' => ['Date or dekad', 'Admin level', 'State/LGA', 'Indicator', 'Data status'],
            'modules' => [
                ['title' => 'Admin-1/admin-2 choropleth', 'type' => 'Map unavailable state', 'status' => 'Provisional until published geometry'],
                ['title' => 'Selected geography trend', 'type' => 'Chart area', 'status' => 'Static interface fixture'],
                ['title' => 'Rainfall records', 'type' => 'Dataset table', 'status' => 'Seeded/API-backed'],
            ],
            'sample_rows' => [
                ['label' => 'rfh', 'period' => 'Dekad', 'unit' => 'Rainfall total', 'state' => 'Plain-language label available'],
                ['label' => '23 admin-2 boundaries', 'period' => 'Current rainfall slice', 'unit' => 'Not applicable', 'state' => 'No value, not join failure'],
            ],
        ],
        'floods' => [
            'title' => 'Where floods have occurred',
            'eyebrow' => 'Floods and disasters',
            'summary' => 'Find flood event records with place, date, metric, source, and unavailable-value states preserved.',
            'question' => 'Where have climate-related disasters occurred, when did they happen, and what was affected?',
            'dataset_codes' => ['nigeria_flood_event_impacts', 'fao_eve_flood_events', 'nigeria_nema_flood_records'],
            'primary_visual' => 'Event map, timeline, and impact table',
            'state_note' => 'Event footprint mapping waits for stable PCODE joins and map-ready responses.',
            'filters' => ['Event type', 'Date range', 'State/LGA', 'Metric', 'Source'],
            'modules' => [
                ['title' => 'Affected geography map', 'type' => 'Map unavailable state', 'status' => 'Provisional until map handoff'],
                ['title' => 'Event timeline', 'type' => 'Timeline', 'status' => 'Static interface fixture'],
                ['title' => 'Impact table', 'type' => 'Dataset table', 'status' => 'Seeded/API-backed'],
            ],
            'sample_rows' => [
                ['label' => 'people_displaced', 'period' => 'Event date', 'unit' => 'People', 'state' => 'Not reported when source value is absent'],
                ['label' => 'affected_people', 'period' => 'Event date', 'unit' => 'People', 'state' => 'Available where source reports value'],
            ],
        ],
        'risk' => [
            'title' => 'Climate risk across Nigerian LGAs',
            'eyebrow' => 'Risk and adaptation',
            'summary' => 'Review risk and adaptation indicators with definitions, denominator notes, and ranked tables before map rendering.',
            'question' => 'Which places face the greatest exposure or vulnerability, and where are readiness gaps visible?',
            'dataset_codes' => ['nigeria_risk_assessment_indicators', 'climatewatch_adaptation_profile'],
            'primary_visual' => 'Risk map and ranked table',
            'state_note' => 'Risk map rendering is provisional until MAP-01 confirms boundary and legend contracts.',
            'filters' => ['Risk dimension', 'Admin level', 'Return period', 'State', 'Definition'],
            'modules' => [
                ['title' => 'LGA risk map', 'type' => 'Map unavailable state', 'status' => 'Static fallback until public geometry'],
                ['title' => 'Ranked LGA table', 'type' => 'Table', 'status' => 'Static interface fixture'],
                ['title' => 'Definition panel', 'type' => 'Method note', 'status' => 'Seeded editorial-backed where available'],
            ],
            'sample_rows' => [
                ['label' => 'Flood exposure', 'period' => 'Source-defined period', 'unit' => 'Score or count', 'state' => 'Requires denominator label'],
                ['label' => 'Readiness gap', 'period' => 'Source-defined period', 'unit' => 'Source-defined score', 'state' => 'Definitions required before ranking'],
            ],
        ],
        'policy' => [
            'title' => 'Climate commitments and policies',
            'eyebrow' => 'Policy',
            'summary' => 'Search climate documents and sector response text without turning source statements into implementation claims.',
            'question' => 'What has Nigeria and other countries committed to, and how are climate actions described by sector?',
            'dataset_codes' => ['nigeria_climate_policy_documents', 'nigeria_document_sector_responses', 'climatewatch_ndc_highlevel', 'climatewatch_ndc_sector_responses', 'climatewatch_ndc_tracker', 'climatewatch_lts_documents', 'climatewatch_pledges'],
            'primary_visual' => 'Document explorer and sector matrix',
            'state_note' => 'Editorial summaries wait for EDIT-01 source and methodology handoff.',
            'filters' => ['Country', 'Document version', 'Sector', 'Target year', 'Response text'],
            'modules' => [
                ['title' => 'Document list', 'type' => 'Searchable table', 'status' => 'Seeded/API-backed'],
                ['title' => 'Sector matrix', 'type' => 'Matrix', 'status' => 'Static interface fixture'],
                ['title' => 'Citation anchors', 'type' => 'Source panel', 'status' => 'Editorial-backed'],
            ],
            'sample_rows' => [
                ['label' => 'NDC', 'period' => 'Submission date', 'unit' => 'Document status', 'state' => 'Repeated versions remain visible'],
                ['label' => 'Sector response', 'period' => 'Document version', 'unit' => 'Response text', 'state' => 'Not implementation evidence'],
            ],
        ],
        'finance' => [
            'title' => 'Where climate finance is going',
            'eyebrow' => 'Climate finance',
            'summary' => 'Review allocation-oriented records with currency, period, recipient, and transaction-state caveats visible.',
            'question' => 'Where is climate finance going, who is implementing it, and which themes or places are covered?',
            'dataset_codes' => ['nigeria_ecological_allocations'],
            'primary_visual' => 'Funding table and theme bars',
            'state_note' => 'Public totals stay unavailable until finance record semantics are confirmed.',
            'filters' => ['Theme', 'Entity', 'Year', 'Status', 'Currency'],
            'modules' => [
                ['title' => 'Finance summary', 'type' => 'Unavailable total state', 'status' => 'Static fixture until semantics confirmed'],
                ['title' => 'Allocation directory', 'type' => 'Table', 'status' => 'Seeded dataset-backed'],
                ['title' => 'Theme bars', 'type' => 'Chart area', 'status' => 'Static interface fixture'],
            ],
            'sample_rows' => [
                ['label' => 'Ecological allocation', 'period' => '2021-2025 source sheets', 'unit' => 'Currency pending field mapping', 'state' => 'Do not call allocation a disbursement'],
                ['label' => 'Project finance', 'period' => 'Approval date', 'unit' => 'Currency', 'state' => 'Planned data family'],
            ],
        ],
        'agriculture' => [
            'title' => 'Agriculture, land and water',
            'eyebrow' => 'Agriculture',
            'summary' => 'Use country-year agriculture, land, water, and food-system indicators with units and source definitions visible.',
            'question' => 'How do agriculture, land use, water use, and food systems connect to climate pressure?',
            'dataset_codes' => ['climatewatch_agriculture_profile', 'nigeria_country_year_indicators'],
            'primary_visual' => 'Indicator trends and dictionary',
            'state_note' => 'Small multiples should be bound after API aggregation fields are final.',
            'filters' => ['Indicator', 'Sub-sector', 'Year', 'Unit', 'Source'],
            'modules' => [
                ['title' => 'Indicator trend', 'type' => 'Chart area', 'status' => 'Static interface fixture'],
                ['title' => 'Indicator dictionary', 'type' => 'Definitions', 'status' => 'Seeded editorial-backed where available'],
                ['title' => 'Availability calendar', 'type' => 'Coverage grid', 'status' => 'Static interface fixture'],
            ],
            'sample_rows' => [
                ['label' => 'Agricultural land', 'period' => 'Country-year', 'unit' => 'Percent or area', 'state' => 'Keep unlike units separate'],
                ['label' => 'Water withdrawal', 'period' => 'Country-year', 'unit' => 'Source-defined denominator', 'state' => 'Definition required'],
            ],
        ],
        'environment' => [
            'title' => "Nigeria's environment in data",
            'eyebrow' => 'Environment',
            'summary' => 'Review environmental and biodiversity indicators without collapsing unlike measures into one unsupported score.',
            'question' => "What is changing in Nigeria's forests, biodiversity, protected areas, and environmental resources?",
            'dataset_codes' => ['nigeria_country_year_indicators', 'climatewatch_agriculture_profile'],
            'primary_visual' => 'Indicator table and inventory map',
            'state_note' => 'Inventory maps wait for confirmed environment-specific schema and MAP-01 geometry contracts.',
            'filters' => ['Indicator', 'Geography', 'Year', 'Source', 'Data type'],
            'modules' => [
                ['title' => 'Forest and biodiversity trends', 'type' => 'Indicator table', 'status' => 'Static interface fixture'],
                ['title' => 'Protected-area map', 'type' => 'Map unavailable state', 'status' => 'Deferred until schema/map contract'],
                ['title' => 'Water-point inventory', 'type' => 'Inventory list', 'status' => 'Static interface fixture'],
            ],
            'sample_rows' => [
                ['label' => 'Forest indicator', 'period' => 'Country-year', 'unit' => 'Source-defined unit', 'state' => 'Do not combine into environment score'],
                ['label' => 'Protected areas', 'period' => 'Source vintage', 'unit' => 'Inventory count or area', 'state' => 'Planned data family'],
            ],
        ],
    ];

    public function home(): View
    {
        $featuredImages = collect(Entry::query()
            ->where('collection', 'pages')
            ->where('slug', 'home')
            ->first()?->value('featured_images') ?? [])
            ->map(function (mixed $reference) {
                if (is_object($reference) && method_exists($reference, 'url')) {
                    return $reference;
                }

                $reference = (string) $reference;

                return Asset::find(str_contains($reference, '::') ? $reference : "assets::{$reference}");
            })
            ->filter()
            ->map(fn ($asset): array => [
                'url' => $asset->url(),
                'alt' => $asset->get('alt') ?: $asset->basename(),
            ])
            ->values();

        $activeDatasets = Dataset::query()
            ->with(['source', 'versions' => fn ($query) => $query
                ->orderByDesc('activated_at')
                ->orderByDesc('id')])
            ->where('status', 'active')
            ->orderBy('title')
            ->get();

        $downloadDatasets = $activeDatasets
            ->where('download_enabled', true)
            ->sortByDesc(fn (Dataset $dataset): int => $dataset->versions->first()?->activated_at?->getTimestamp() ?? 0)
            ->take(5)
            ->values();

        $recordCount = $activeDatasets->sum(fn (Dataset $dataset) => (int) optional($dataset->versions->first())->row_count);
        $emissionsDataset = $activeDatasets->firstWhere('code', 'climatewatch_historical_emissions');
        $emissionsVersion = $emissionsDataset?->versions->first();
        $emissionsSummary = collect();
        $emissionsSummaryYear = null;
        $emissionsContext = null;

        if ($emissionsVersion) {
            $emissionsSummaryYear = CountryYearSectorGasValue::query()
                ->where('dataset_version_id', $emissionsVersion->id)
                ->max('year');

            $countryLabel = static function (string $code): string {
                return class_exists(\Locale::class) ? \Locale::getDisplayRegion('und_'.$code, 'en') : $code;
            };
            $emissionsSummary = CountryYearSectorGasValue::query()
                ->where('dataset_version_id', $emissionsVersion->id)
                ->where('year', $emissionsSummaryYear)
                ->whereHas('gas', fn (Builder $query) => $query->whereIn('code', ['all_ghg', 'kyotoghg']))
                ->whereHas('sector', fn (Builder $query) => $query->where('code', 'energy'))
                ->get(['country_code', 'value'])
                ->unique('country_code')
                ->map(fn (CountryYearSectorGasValue $row): array => [
                    'code' => $row->country_code,
                    'country' => $countryLabel($row->country_code),
                    'value' => round((float) $row->value, 1),
                ])
                ->values();

            $rankedEmissions = $emissionsSummary->sortByDesc('value')->values();
            $nigeriaPosition = $rankedEmissions->search(fn (array $point): bool => $point['code'] === 'NGA');

            if ($nigeriaPosition !== false) {
                $emissionsContext = [
                    'value' => $rankedEmissions[$nigeriaPosition]['value'],
                    'rank' => $nigeriaPosition + 1,
                    'country_count' => $rankedEmissions->count(),
                ];
            }
        }

        return view('homepage', [
            'downloadDatasets' => $downloadDatasets,
            'topics' => $this->topicCards(),
            'datasetCount' => $activeDatasets->count(),
            'sourceCount' => Source::query()->count(),
            'recordCount' => $recordCount,
            'emissionsSummary' => $emissionsSummary,
            'emissionsSummaryYear' => $emissionsSummaryYear,
            'emissionsContext' => $emissionsContext,
            'featuredImages' => $featuredImages,
            'datasetTopics' => $this->datasetTopics(),
        ]);
    }

    public function index(): RedirectResponse
    {
        return to_route('downloads');
    }

    public function topics(): View
    {
        return view('public.topics', ['topics' => $this->topicCards()]);
    }

    public function search(): RedirectResponse
    {
        return to_route('downloads');
    }

    public function show(Dataset $dataset): View
    {
        abort_unless($dataset->status === 'active' && $dataset->api_enabled, 404);

        $dataset->load([
            'source',
            'versions' => fn ($query) => $query
                ->orderByDesc('activated_at')
                ->orderByDesc('id'),
        ]);

        return view('datasets.show', [
            'dataset' => $dataset,
            'datasetCode' => $dataset->code,
        ]);
    }

    public function downloads(Request $request): View
    {
        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, ['dataset', 'topics', 'source', 'downloads', 'file'], true) ? $sort : 'dataset';
        $direction = $request->string('direction')->lower()->toString() === 'desc' ? 'desc' : 'asc';
        $datasetTopics = $this->datasetTopics();
        $datasets = $this->activeDatasets()->get();

        $datasets = $datasets
            ->sortBy(function (Dataset $dataset) use ($sort, $datasetTopics): string|int {
                return match ($sort) {
                    'topics' => $datasetTopics->get($dataset->code, collect())->pluck('title')->join(' ') ?: 'Unclassified',
                    'source' => $dataset->source?->name ?? 'Source unavailable',
                    'downloads' => $dataset->download_count,
                    'file' => $dataset->download_enabled && $dataset->api_enabled ? 1 : 0,
                    default => $dataset->title,
                };
            }, SORT_NATURAL | SORT_FLAG_CASE, $direction === 'desc')
            ->values();

        $perPage = 10;
        $page = max($request->integer('page', 1), 1);
        $datasets = new LengthAwarePaginator(
            $datasets->forPage($page, $perPage)->values(),
            $datasets->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('public.downloads', [
            'datasets' => $datasets,
            'datasetTopics' => $datasetTopics,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function country(string $countryCode): View
    {
        return view('public.profile', [
            'kind' => 'country',
            'code' => strtoupper($countryCode),
            'title' => strtoupper($countryCode) === 'NG' ? 'Nigeria climate profile' : strtoupper($countryCode).' climate profile',
            'summary' => 'A country-level starting point for emissions, rainfall, risk, policy, finance, agriculture, and environment evidence.',
            'datasets' => $this->activeDatasets()->limit(8)->get(),
            'mapStatus' => 'Country map and exact profile values require the API and MAP handoffs before final binding.',
            'sections' => $this->profileSections('country'),
        ]);
    }

    public function geography(string $pcode): View
    {
        return view('public.profile', [
            'kind' => 'geography',
            'code' => strtoupper($pcode),
            'title' => strtoupper($pcode).' climate profile',
            'summary' => 'A geography-level workspace for available rainfall, flood, risk, water, agriculture, and finance evidence.',
            'datasets' => $this->activeDatasets(['admin_period_indicator', 'event_impact'])->get(),
            'mapStatus' => 'Boundary highlight, parent hierarchy, and unavailable-measure states require the MAP-01 handoff before final binding.',
            'sections' => $this->profileSections('geography'),
        ]);
    }

    public function story(string $slug): View
    {
        return view('public.story', [
            'slug' => $slug,
            'title' => str($slug)->replace('-', ' ')->headline(),
            'datasets' => $this->activeDatasets(['admin_period_indicator', 'event_impact'])->limit(4)->get(),
            'storyModules' => $this->storyModules($slug),
        ]);
    }

    public function theme(Request $request, string $theme): View
    {
        $config = self::THEME_CONFIG[$theme];

        if (! in_array($theme, ['emissions', 'rainfall'], true)) {
            return view('public.topic-ingesting', [
                'topic' => $config['title'],
            ]);
        }

        $datasets = $this->datasetsByCode($config['dataset_codes']);

        if ($theme === 'emissions') {
            $selectedDataset = $datasets->firstWhere('code', 'climatewatch_historical_emissions');

            return view('public.emissions', [
                'dataset' => $selectedDataset,
                'emissionsDashboard' => $this->emissionsDashboard($selectedDataset, $request),
            ]);
        }

        $selectedDataset = $datasets->first();

        return view('public.rainfall', [
            'dataset' => $selectedDataset,
            'rainfallDashboard' => $this->rainfallDashboard($selectedDataset),
        ]);
    }

    /** @return array{indicators: array<int, array<string, mixed>>, periods: array<int, string>, default_indicator: string, default_period: string|null, source_name: string|null, version_label: string|null, record_count: int} */
    private function rainfallDashboard(?Dataset $dataset): array
    {
        $version = $dataset?->versions->first();

        if (! $version) {
            return [
                'indicators' => [],
                'periods' => [],
                'default_indicator' => 'rfh',
                'default_period' => null,
                'source_name' => $dataset?->source?->name,
                'version_label' => null,
                'record_count' => 0,
            ];
        }

        $rows = AdminPeriodIndicatorValue::query()
            ->with('indicator.unit')
            ->where('dataset_version_id', $version->id)
            ->whereHas('geography', fn (Builder $query) => $query->where('level', 1))
            ->orderByDesc('period_date')
            ->get();

        $indicators = $rows
            ->map(fn (AdminPeriodIndicatorValue $row): ?Indicator => $row->indicator)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->map(fn (Indicator $indicator): array => [
                'code' => $indicator->code,
                'label' => $indicator->name,
                'unit' => $indicator->unit?->symbol ?? $indicator->unit?->name,
            ])
            ->values()
            ->all();
        $periods = $rows->pluck('period_date')->map(fn ($date): string => $date->toDateString())->unique()->take(12)->values()->all();

        return [
            'indicators' => $indicators,
            'periods' => $periods,
            'default_indicator' => collect($indicators)->contains('code', 'rfh') ? 'rfh' : ($indicators[0]['code'] ?? 'rfh'),
            'default_period' => $periods[0] ?? null,
            'source_name' => $dataset?->source?->name,
            'version_label' => $version->version_label,
            'record_count' => (int) $version->row_count,
        ];
    }

    /** @return array{is_sample: bool, source_name: string|null, last_updated_label: string|null, comparison_data: array<string, mixed>} */
    private function emissionsDashboard(?Dataset $dataset, Request $request): array
    {
        $version = $dataset?->versions->first();

        if (! $version) {
            return [
                'is_sample' => false,
                'source_name' => $dataset?->source?->name,
                'last_updated_label' => null,
                'comparison_data' => ['countries' => [], 'sectors' => [], 'gases' => [], 'years' => [], 'records' => []],
            ];
        }

        $versionTimestamp = $version->updated_at?->getTimestamp() ?? 0;
        $cacheKey = 'emissions.dashboard.config.'.$version->id.'.'.$versionTimestamp;

        return Cache::remember($cacheKey, 900, function () use ($dataset, $version): array {
            $countryCodes = CountryYearSectorGasValue::query()
                ->where('dataset_version_id', $version->id)
                ->distinct()
                ->orderBy('country_code')
                ->pluck('country_code');
            $gasRows = CountryYearSectorGasValue::query()
                ->with('gas')
                ->where('dataset_version_id', $version->id)
                ->select('gas_id')
                ->distinct()
                ->get();
            $sectorRows = CountryYearSectorGasValue::query()
                ->with('sector')
                ->where('dataset_version_id', $version->id)
                ->select('sector_id')
                ->distinct()
                ->get();
            $gasSectorRows = CountryYearSectorGasValue::query()
                ->with(['gas:id,code', 'sector:id,code'])
                ->where('dataset_version_id', $version->id)
                ->select(['gas_id', 'sector_id'])
                ->distinct()
                ->get();
            $years = CountryYearSectorGasValue::query()
                ->where('dataset_version_id', $version->id)
                ->distinct()
                ->orderBy('year')
                ->pluck('year')
                ->map(fn (int $year): int => $year)
                ->all();

            $countryNames = [
                'BRA' => 'Brazil', 'GHA' => 'Ghana', 'IND' => 'India', 'KEN' => 'Kenya',
                'NGA' => 'Nigeria', 'ZAF' => 'South Africa',
            ];
            $countryLabel = static function (string $code) use ($countryNames): string {
                if (isset($countryNames[$code])) {
                    return $countryNames[$code];
                }

                $label = class_exists(\Locale::class) ? \Locale::getDisplayRegion('und_'.$code, 'en') : '';

                return filled($label) ? $label : $code;
            };
            $gases = $gasRows->mapWithKeys(fn (CountryYearSectorGasValue $row): array => [
                $row->gas?->code => in_array(strtolower((string) $row->gas?->code), ['all_ghg', 'kyotoghg'], true)
                    ? strtolower((string) $row->gas?->code) === 'kyotoghg'
                        ? 'Kyoto GHG (source aggregate)'
                        : 'Total GHG (source aggregate)'
                    : $row->gas?->name,
            ])->filter()->sort()->map(fn (string $label, string $code): array => [
                'code' => $code,
                'label' => $label,
            ])->values();
            $defaultGas = $gases->first(fn (array $gas): bool => strtolower($gas['code']) === 'kyotoghg')['code']
                ?? $gases->first(fn (array $gas): bool => strtolower($gas['code']) === 'all_ghg')['code']
                ?? $gases->first()['code']
                ?? null;
            $aggregateGases = $gases->filter(fn (array $gas): bool => in_array(strtolower($gas['code']), ['all_ghg', 'kyotoghg'], true));
            $componentGases = $gases->reject(fn (array $gas): bool => in_array(strtolower($gas['code']), ['all_ghg', 'kyotoghg'], true));

            return [
                'is_sample' => false,
                'source_name' => $dataset?->source?->name,
                'last_updated_label' => ($version->import_completed_at ?? $version->activated_at ?? $version->updated_at ?? $version->created_at)?->format('j M Y'),
                'comparison_data' => [
                    'countries' => $countryCodes->map(fn (string $code): array => [
                        'code' => $code,
                        'label' => $countryLabel($code),
                    ])->values()->all(),
                    'gases' => $aggregateGases->concat($componentGases)->values()->all(),
                    'default_gas' => $defaultGas,
                    'source_name' => $dataset?->source?->name,
                    'has_individual_gases' => $componentGases->isNotEmpty(),
                    'years' => $years,
                    'sectors' => $sectorRows->mapWithKeys(fn (CountryYearSectorGasValue $row): array => [
                        $row->sector?->code => $row->sector?->name,
                    ])->filter()->unique()->sort()->map(fn (string $label, string $code): array => [
                        'code' => $code,
                        'label' => $label,
                    ])->values()->all(),
                    'gas_sectors' => $gasSectorRows
                        ->filter(fn (CountryYearSectorGasValue $row): bool => $row->gas && $row->sector)
                        ->groupBy(fn (CountryYearSectorGasValue $row): string => $row->gas->code)
                        ->map(fn ($rows): array => $rows->pluck('sector.code')->unique()->values()->all())
                        ->all(),
                    'records' => [],
                ],
            ];
        });
    }

    private function activeDatasets(array $datasetTypes = []): Builder
    {
        return Dataset::query()
            ->with(['source', 'versions' => fn ($query) => $query
                ->orderByDesc('activated_at')
                ->orderByDesc('id')])
            ->where('status', 'active')
            ->when($datasetTypes !== [], fn (Builder $query) => $query->whereIn('dataset_type', $datasetTypes))
            ->orderBy('title');
    }

    /**
     * @return Collection<string, Collection<int, array{title: string, url: string}>>
     */
    private function datasetTopics(): Collection
    {
        return Entry::query()
            ->where('collection', 'datasets')
            ->get()
            ->mapWithKeys(function ($entry): array {
                $datasetCode = $entry->value('dataset_code');

                if (! filled($datasetCode)) {
                    return [];
                }

                $topics = collect($entry->value('topics') ?? [])
                    ->map(function (mixed $reference) {
                        if (is_object($reference) && method_exists($reference, 'url')) {
                            return $reference;
                        }

                        $reference = (string) $reference;

                        return Term::find(str_contains($reference, '::') ? $reference : "topics::{$reference}");
                    })
                    ->filter()
                    ->map(fn ($topic): array => [
                        'title' => $topic->value('title') ?: $topic->slug(),
                        'url' => $topic->url(),
                    ])
                    ->values();

                return [(string) $datasetCode => $topics];
            });
    }

    private function topicCards(): array
    {
        return [
            ['title' => 'Emissions', 'icon' => 'factory', 'href' => '/emissions', 'summary' => 'Compare country emissions by sector, gas, source, and year.'],
            ['title' => 'Rainfall', 'icon' => 'cloudy_snowing', 'href' => '/rainfall', 'summary' => 'Explore reported rainfall across Nigerian states and local government areas.'],
            ['title' => 'Floods', 'icon' => 'flood', 'href' => '/floods', 'summary' => 'Review reported flood events and their recorded impacts.'],
            ['title' => 'Risk', 'icon' => 'warning', 'href' => '/risk', 'summary' => 'Compare climate risk indicators across Nigerian local areas.'],
            ['title' => 'Policy', 'icon' => 'policy', 'href' => '/policy', 'summary' => 'Find climate commitments and policy-document evidence.'],
            ['title' => 'Finance', 'icon' => 'payments', 'href' => '/finance', 'summary' => 'Explore climate finance and funding data.'],
            ['title' => 'Environment', 'icon' => 'eco', 'href' => '/environment', 'summary' => 'Review environmental indicators and source context.'],
        ];
    }

    private function datasetsByCode(array $codes): Collection
    {
        return $this->activeDatasets()
            ->whereIn('code', $codes)
            ->get()
            ->sortBy(fn (Dataset $dataset) => array_search($dataset->code, $codes, true))
            ->values();
    }

    private function profileSections(string $kind): array
    {
        if ($kind === 'country') {
            return [
                ['title' => 'Emissions trend', 'state' => 'Seeded records available; final chart aggregation pending.'],
                ['title' => 'Rainfall and hazards', 'state' => 'Rainfall records available; map binding provisional.'],
                ['title' => 'Policy and NDCs', 'state' => 'Document datasets available with citation context.'],
                ['title' => 'Finance and environment', 'state' => 'Static interface fixture until record semantics are finalized.'],
            ];
        }

        return [
            ['title' => 'Boundary and hierarchy', 'state' => 'Map unavailable until published geometry is approved.'],
            ['title' => 'Rainfall coverage', 'state' => 'No-data and no-value states must remain visible.'],
            ['title' => 'Flood events', 'state' => 'Event rows available where source geography resolves.'],
            ['title' => 'Ward drilldown', 'state' => 'Unavailable pending INEC wardcode-to-PCODE crosswalk.'],
        ];
    }

    private function storyModules(string $slug): array
    {
        return [
            ['title' => 'Opening evidence', 'state' => 'Static story fixture linked to live dataset cards.'],
            ['title' => 'Rainfall context', 'state' => 'Use rainfall table until public map binding is approved.'],
            ['title' => 'Flood impact records', 'state' => 'Show unavailable impact values as not reported.'],
            ['title' => 'Method and caveats', 'state' => 'Use seeded methodology and source notes; do not invent narrative claims.'],
        ];
    }
}

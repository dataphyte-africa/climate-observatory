@extends('layouts.dataset-browser', [
    'title' => $theme === 'emissions' && ($selectedDataset?->code ?? null) !== 'nigeria_climate_trace_emissions' ? 'Global country emissions comparison' : $config['title'],
    'activeNav' => 'themes',
])

@php
    $primaryDataset = $selectedDataset ?? $datasets->first();
    $latestVersion = $primaryDataset?->versions->first();
    $mapThemes = ['rainfall', 'floods', 'risk'];
    $isMapTheme = in_array($theme, $mapThemes, true);
    $isClimateTraceMode = $theme === 'emissions' && $primaryDataset?->code === 'nigeria_climate_trace_emissions';
    $filterDefaults = [
        'Date or dekad' => '05/01/2024',
        'Admin level' => 'State',
        'State/LGA' => 'Kano State',
        'Indicator' => 'Total Rainfall (mm)',
        'Data status' => 'Final',
        'Metric' => 'All GHG',
        'Year range' => '1990 - 2023',
        'Sector' => 'All sectors',
        'Gas' => 'All gases',
        'Source series' => 'Primary',
        'Country' => 'Nigeria',
        'Document version' => 'Latest',
        'Target year' => 'All',
        'Response text' => 'All',
        'Theme' => 'All themes',
        'Entity' => 'All entities',
        'Year' => '2024',
        'Status' => 'All statuses',
        'Currency' => 'Source currency',
        'Event type' => 'Flood',
        'Date range' => '2012 - 2024',
        'State' => 'All states',
        'Risk dimension' => 'Flood exposure',
        'Return period' => 'Source period',
        'Definition' => 'All definitions',
        'Sub-sector' => 'All sub-sectors',
        'Unit' => 'Source unit',
        'Source' => 'All sources',
        'Geography' => 'Nigeria',
        'Data type' => 'Indicator',
    ];
@endphp

@section('content')
    <main class="ch-shell py-6">
        <section class="mb-4">
            <div class="flex flex-wrap items-center gap-3 text-xs text-on-surface-variant">
                <a class="hover:text-secondary" href="/explore">Explore data</a>
                <span class="material-symbols-outlined text-[14px]" aria-hidden="true">chevron_right</span>
                <span>{{ $config['eyebrow'] }}</span>
            </div>
            <h1 class="mt-2 font-headline text-2xl font-semibold leading-8 text-primary md:text-4xl md:leading-10">{{ $theme === 'emissions' && ! $isClimateTraceMode ? 'Global country emissions comparison' : $config['title'] }}</h1>
            <p class="mt-1 max-w-3xl text-sm leading-5 text-on-surface-variant">{{ $theme === 'emissions' && ! $isClimateTraceMode ? 'Compare available country-level greenhouse-gas series by country, period, and sector. Nigeria subnational data remains in the separate Climate TRACE view.' : $config['summary'] }}</p>
        </section>

        @if ($theme === 'emissions')
            <section class="mb-4 rounded-lg border border-outline-variant bg-surface-container-lowest p-4" aria-labelledby="emissions-dataset-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="emissions-dataset-heading" class="font-headline text-lg font-semibold text-primary">Choose an emissions dataset</h2>
                        <p class="mt-1 text-sm text-on-surface-variant">These sources are separate datasets. They are not merged because their geographic coverage and measures differ.</p>
                    </div>
                    <a class="text-xs font-semibold uppercase tracking-[0.05em] text-secondary hover:underline" href="/explore?query=emissions">Browse all emissions data</a>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <a href="/emissions?dataset=climatewatch_historical_emissions" @class([
                        'rounded border p-4 transition-colors hover:border-secondary hover:bg-secondary-container/30',
                        'border-primary bg-primary text-white' => ! $isClimateTraceMode,
                        'border-outline-variant bg-surface' => $isClimateTraceMode,
                    ]) aria-current="{{ ! $isClimateTraceMode ? 'page' : 'false' }}">
                        <div>
                            <p @class(['text-xs font-semibold uppercase tracking-[0.05em]', 'text-white/80' => ! $isClimateTraceMode, 'text-secondary' => $isClimateTraceMode])>Global country comparison</p>
                            <h3 @class(['mt-1 font-headline text-lg font-semibold', 'text-white' => ! $isClimateTraceMode, 'text-primary' => $isClimateTraceMode])>Climate Watch Historical Emissions</h3>
                        </div>
                        <p @class(['mt-2 text-sm leading-5', 'text-white/85' => ! $isClimateTraceMode, 'text-on-surface-variant' => $isClimateTraceMode])>Country-year greenhouse-gas series. Select Nigeria or compare countries, sources, sectors, gases, and years.</p>
                    </a>
                    <a href="/emissions?dataset=nigeria_climate_trace_emissions" @class([
                        'rounded border p-4 transition-colors hover:border-secondary hover:bg-secondary-container/30',
                        'border-primary bg-primary text-white' => $isClimateTraceMode,
                        'border-outline-variant bg-surface' => ! $isClimateTraceMode,
                    ]) aria-current="{{ $isClimateTraceMode ? 'page' : 'false' }}">
                        <div>
                            <p @class(['text-xs font-semibold uppercase tracking-[0.05em]', 'text-white/80' => $isClimateTraceMode, 'text-secondary' => ! $isClimateTraceMode])>Nigeria subnational exploration</p>
                            <h3 @class(['mt-1 font-headline text-lg font-semibold', 'text-white' => $isClimateTraceMode, 'text-primary' => ! $isClimateTraceMode])>Nigeria Climate TRACE Emissions and Air Pollutants</h3>
                        </div>
                        <p @class(['mt-2 text-sm leading-5', 'text-white/85' => $isClimateTraceMode, 'text-on-surface-variant' => ! $isClimateTraceMode])>Nigeria source material includes administrative-level and city extracts. State, LGA, and city views require granular records to be validated and published separately.</p>
                    </a>
                </div>
            </section>
        @endif

        @if ($theme !== 'emissions')
        <div class="mb-4 flex items-start gap-3 rounded border border-[#ffb340] bg-[#fff4e5] p-4 text-[#663c00]">
            <span class="material-symbols-outlined text-[#401c00]" aria-hidden="true">warning</span>
            <div>
                <h2 class="text-sm font-semibold text-[#401c00]">Partial Coverage Alert</h2>
                <p class="mt-1 text-sm leading-5">{{ $config['state_note'] }}</p>
            </div>
        </div>
        @endif

        @if ($theme === 'emissions' && ! $isClimateTraceMode)
            <div x-data="emissionsComparison(@js($emissionsDashboard['comparison_data']))" class="space-y-4">
            <section class="rounded-lg border border-outline-variant bg-surface-container-lowest p-4">
                <div class="flex flex-wrap items-end gap-3">
                    <fieldset class="min-w-48 flex-1">
                        <legend class="text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant">Reporting years</legend>
                        <div class="mt-1 flex flex-wrap gap-2">
                            <template x-for="year in availableYears" :key="year">
                                <label class="cursor-pointer">
                                    <input class="peer sr-only" type="checkbox" x-model="selectedYears" :value="String(year)">
                                    <span class="block rounded border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface-variant transition-colors peer-checked:border-secondary peer-checked:bg-secondary peer-checked:text-white" x-text="year"></span>
                                </label>
                            </template>
                        </div>
                    </fieldset>
                    <label class="flex min-w-48 flex-1 flex-col gap-1">
                        <span class="text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant">Sector</span>
                        <select class="ch-input py-2" x-model="selectedSector">
                            <option value="">All sectors</option>
                            <template x-for="sector in sectors" :key="sector.code">
                                <option :value="sector.code" x-text="sector.label"></option>
                            </template>
                        </select>
                    </label>
                    <div class="flex min-w-48 flex-1 flex-col gap-1">
                        <span class="text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant">Gas</span>
                        <p class="ch-input flex items-center py-2 text-sm">All GHG</p>
                    </div>
                    <p class="flex h-[42px] items-center text-xs text-on-surface-variant">Updates instantly</p>
                </div>
            </section>
        @elseif ($theme !== 'emissions')
        <section class="mb-4 rounded-lg border border-outline-variant bg-surface-container-lowest p-4">
            <h2 class="sr-only">Filters</h2>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3 lg:grid-cols-6">
                @foreach ($config['filters'] as $filter)
                    <label class="flex flex-col gap-1">
                        <span class="text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant">{{ $filter }}</span>
                        <select class="ch-input py-2">
                            <option>{{ $filterDefaults[$filter] ?? 'All' }}</option>
                            <option>Unavailable</option>
                            <option>Partial coverage</option>
                        </select>
                    </label>
                @endforeach
                <div class="flex items-end">
                    <button class="ch-btn-primary h-[42px] w-full" type="button">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">filter_alt</span>
                        Apply Filters
                    </button>
                </div>
            </div>
        </section>
        @endif

        <section class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            <div class="space-y-4 {{ $theme === 'emissions' ? '' : 'lg:col-span-8' }}" @if ($theme === 'emissions') style="grid-column: 1 / -1;" @endif>
                <article class="overflow-hidden rounded-lg border border-outline-variant bg-surface-container-lowest">
                    <div class="flex h-12 items-center justify-between bg-primary-container px-4 text-on-primary">
                        <h2 class="font-headline text-base font-semibold">{{ $isMapTheme ? ($theme === 'rainfall' ? 'Rainfall Intensity Map (mm)' : $config['primary_visual']) : $config['primary_visual'] }}</h2>
                        @if ($theme !== 'emissions')
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">search</span>
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">layers</span>
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">my_location</span>
                        </div>
                        @endif
                    </div>

                    @if ($theme === 'emissions' && ! $isClimateTraceMode)
                        <div class="bg-white p-5">
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-on-surface-variant">
                                    <template x-for="series in chartSeries" :key="series.label">
                                        <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full" :style="`background-color: ${series.color}`"></span><span x-text="series.label"></span></span>
                                    </template>
                                </div>
                                <span class="rounded border border-outline-variant bg-surface px-3 py-1.5 text-xs text-on-surface-variant">Global coverage · country comparison</span>
                            </div>
                            @if ($emissionsDashboard['is_sample'])
                                <p class="mb-3 text-xs font-medium text-on-surface-variant">Development sample values for interface testing. Replace with reviewed source records before production release.</p>
                            @endif
                            <div class="overflow-x-auto">
                                <canvas x-ref="chart" class="block w-full min-w-[640px]" width="720" height="290" role="img" aria-label="Country emissions comparison by selected reporting years"></canvas>
                            </div>
                        </div>
                    @elseif ($theme === 'emissions' && $isClimateTraceMode)
                        <div class="relative flex min-h-[460px] items-center justify-center overflow-hidden bg-surface-container-low p-5">
                            <img class="absolute inset-0 h-full w-full object-contain opacity-30" src="/images/nigeria-coverage-map.png" alt="">
                            <div class="relative max-w-md rounded-lg border border-outline-variant bg-surface-container-lowest p-5 text-center shadow-sm">
                                <span class="material-symbols-outlined text-4xl text-secondary" aria-hidden="true">location_city</span>
                                <h3 class="mt-3 font-headline text-xl font-semibold text-primary">Nigeria state and city emissions</h3>
                                <p class="mt-2 text-sm leading-6 text-on-surface-variant">Climate TRACE source material has Nigeria administrative and city extracts. A choropleth or city layer will appear here only after those granular records have canonical geography or coordinates, pass review, and are published.</p>
                                <a class="ch-btn-secondary mt-4" href="/datasets/nigeria_climate_trace_emissions">View Climate TRACE dataset metadata</a>
                            </div>
                        </div>
                    @elseif ($isMapTheme)
                        <div class="relative h-[520px] overflow-hidden bg-surface-container-low">
                            <img class="absolute inset-0 h-full w-full object-contain opacity-75" src="/images/nigeria-coverage-map.png" alt="">
                            <div class="absolute left-4 top-4 w-44 rounded border border-outline-variant bg-white p-3 text-xs text-on-surface-variant shadow-sm">
                                <div class="mb-2 font-semibold text-primary">{{ $theme === 'rainfall' ? 'Rainfall layers' : 'Map layers' }}</div>
                                <label class="flex items-center gap-2 py-1"><input checked type="checkbox" class="rounded border-outline-variant text-secondary"> State boundary</label>
                                <label class="flex items-center gap-2 py-1"><input type="checkbox" class="rounded border-outline-variant text-secondary"> LGA boundary</label>
                                <label class="flex items-center gap-2 py-1"><input checked type="checkbox" class="rounded border-outline-variant text-secondary"> No-value state</label>
                            </div>
                            <div class="absolute bottom-4 left-4 rounded border border-outline-variant bg-white p-3 text-xs shadow-sm">
                                <div class="mb-2 font-semibold text-primary">Legend</div>
                                <div class="flex items-center gap-2">
                                    <span>Low</span>
                                    <div class="h-3 w-32 rounded bg-gradient-to-r from-[#ffffd4] via-[#41b6c4] to-[#081d58]"></div>
                                    <span>High</span>
                                </div>
                                <p class="mt-2 max-w-[220px] leading-5 text-on-surface-variant">Map unavailable: public geometry binding waits for MAP/API/QA evidence.</p>
                            </div>
                            <div class="absolute right-4 top-4 rounded border border-outline-variant bg-white p-3 text-xs text-on-surface-variant shadow-sm">
                                <span class="font-semibold text-primary">No-value areas preserved</span>
                                <p class="mt-1 max-w-[210px] leading-5">23 admin-2 boundaries with no rainfall rows render as no-value states.</p>
                            </div>
                        </div>
                    @elseif ($theme === 'policy')
                        <div class="grid gap-4 bg-white p-5 md:grid-cols-2">
                            @foreach (['NDC High-Level Content', 'Sector Responses', 'Long-Term Strategies', 'Climate Pledges'] as $label)
                                <article class="rounded border border-outline-variant bg-surface p-4">
                                    <div class="mb-3 flex items-center justify-between">
                                        <span class="ch-chip">Document</span>
                                        <span class="material-symbols-outlined text-outline" aria-hidden="true">description</span>
                                    </div>
                                    <h3 class="font-headline text-xl font-semibold text-primary">{{ $label }}</h3>
                                    <p class="mt-2 text-sm leading-5 text-on-surface-variant">Citation anchors and document versions remain visible; source statements are not presented as implementation evidence.</p>
                                </article>
                            @endforeach
                            <div class="rounded border border-outline-variant bg-surface-container-low p-4 md:col-span-2">
                                <h3 class="font-semibold text-primary">Sector matrix</h3>
                                <div class="mt-4 grid grid-cols-3 gap-2 text-sm">
                                    @foreach (['Energy', 'AFOLU', 'Waste', 'Transport', 'Industry', 'Water'] as $sector)
                                        <div class="rounded border border-outline-variant bg-white p-3">{{ $sector }}</div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @elseif ($theme === 'finance')
                        <div class="grid gap-4 bg-white p-5 md:grid-cols-3">
                            @foreach ([['Ecological allocation', 72, 'Unavailable total state'], ['Adaptation', 46, 'Static fixture'], ['Mitigation', 58, 'Semantics pending']] as $item)
                                <article class="rounded border border-outline-variant bg-surface p-4">
                                    <span class="ch-chip">Finance</span>
                                    <h3 class="mt-3 font-headline text-xl font-semibold text-primary">{{ $item[0] }}</h3>
                                    <p class="mt-2 text-sm text-on-surface-variant">{{ $item[2] }}</p>
                                    <div class="mt-5 h-2 rounded bg-surface-variant">
                                        <div class="h-2 rounded bg-secondary" style="width: {{ $item[1] }}%"></div>
                                    </div>
                                </article>
                            @endforeach
                            <div class="rounded border border-outline-variant bg-error-container p-4 text-sm text-on-error-container md:col-span-3">
                                Public totals stay unavailable until finance record semantics are confirmed. Do not call allocation a disbursement.
                            </div>
                        </div>
                    @else
                        <div class="grid gap-5 bg-white p-5 lg:grid-cols-[1fr_0.9fr]">
                            <div class="relative min-h-[360px] rounded border border-outline-variant bg-surface p-5">
                                <h3 class="font-headline text-xl font-semibold text-primary">Indicator trends</h3>
                                <div class="mt-8 flex h-56 items-end gap-3">
                                    @foreach ([42, 55, 48, 68, 60, 76, 66, 83] as $height)
                                        <div class="flex flex-1 flex-col justify-end gap-2">
                                            <div class="rounded-t bg-primary-container" style="height: {{ $height }}%"></div>
                                            <span class="text-center text-[10px] text-on-surface-variant">{{ 2016 + $loop->index }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="grid gap-3">
                                @foreach ($config['sample_rows'] as $row)
                                    <div class="rounded border border-outline-variant bg-surface p-4">
                                        <h3 class="font-semibold text-primary">{{ $row['label'] }}</h3>
                                        <p class="mt-2 text-sm leading-5 text-on-surface-variant">{{ $row['unit'] }} · {{ $row['state'] }}</p>
                                    </div>
                                @endforeach
                                @if ($theme === 'environment')
                                    <div class="rounded border border-outline-variant bg-surface-container-low p-4 text-sm text-on-surface-variant">Inventory maps wait for confirmed schema and MAP-01 geometry contracts.</div>
                                @endif
                            </div>
                        </div>
                    @endif
                </article>

                <article class="overflow-hidden rounded-lg border border-outline-variant bg-surface-container-lowest">
                    <div class="flex h-12 items-center justify-between bg-secondary px-4 text-on-secondary">
                        <h2 class="font-headline text-base font-semibold">{{ $theme === 'emissions' && ! $isClimateTraceMode ? 'Latest country totals' : $config['modules'][1]['title'] }}</h2>
                        <span class="rounded bg-white/20 px-2 py-1 text-xs font-semibold" @if ($theme === 'emissions' && ! $isClimateTraceMode) x-text="latestYear ?? 'No data'" @endif>{{ $theme === 'emissions' && ! $isClimateTraceMode ? '' : '2014 - 2024' }}</span>
                    </div>
                    @if ($theme === 'emissions' && ! $isClimateTraceMode)
                        <div class="grid gap-4 bg-white p-5 md:grid-cols-3">
                            <template x-for="country in latestTotals" :key="country.country">
                                <div class="rounded border border-outline-variant bg-surface p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-sm font-semibold text-primary" x-text="country.country"></span>
                                        <span class="h-3 w-3 rounded-full" :style="`background-color: ${country.color}`"></span>
                                    </div>
                                    <p class="mt-4 font-headline text-2xl font-semibold text-primary" x-text="formatNumber(country.value)"></p>
                                    <p class="mt-1 text-xs text-on-surface-variant"><span x-text="latestYear"></span> total for the selected filter</p>
                                </div>
                            </template>
                        </div>
                    @else
                    <div class="h-[260px] bg-white p-5">
                        <div class="flex h-full items-end gap-3">
                            @foreach ([36, 48, 42, 67, 58, 72, 64, 83, 76, 90] as $height)
                                <div class="flex flex-1 flex-col justify-end gap-2">
                                    <div class="rounded-t bg-secondary" style="height: {{ $height }}%"></div>
                                    <span class="text-center text-[10px] text-on-surface-variant">{{ 2014 + $loop->index }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </article>
            </div>

            @if ($theme !== 'emissions')
            <aside class="space-y-4 lg:col-span-4">
                <section class="rounded-lg border border-outline-variant bg-surface-container-lowest">
                    <div class="border-b border-outline-variant p-4">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <h2 class="font-headline text-xl font-semibold text-primary">{{ $theme === 'rainfall' ? 'Kano State' : 'Nigeria' }}</h2>
                            <a class="text-xs font-semibold uppercase tracking-[0.05em] text-secondary hover:underline" href="/explore">View dataset</a>
                        </div>
                        <p class="text-sm leading-5 text-on-surface-variant">Source, unit, period, and state context</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 p-4">
                        @foreach ($config['sample_rows'] as $row)
                            <div class="rounded border border-outline-variant bg-surface p-3">
                                <span class="block text-xs text-on-surface-variant">{{ $row['label'] }}</span>
                                <span class="mt-2 block font-headline text-lg font-semibold text-primary">{{ $row['period'] }}</span>
                                <span class="mt-1 block text-xs text-on-surface-variant">{{ $row['state'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-lg border border-outline-variant bg-surface-container-lowest p-4 text-sm">
                    <h2 class="mb-3 flex items-center gap-2 font-headline text-xl font-semibold text-primary">
                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">info</span>
                        Dataset Metadata
                    </h2>
                    <dl class="grid grid-cols-3 gap-x-3 gap-y-3">
                        <dt class="text-on-surface-variant">Source:</dt>
                        <dd class="col-span-2 text-on-surface">{{ $primaryDataset?->source?->name ?? 'Source unavailable' }}</dd>
                        <dt class="text-on-surface-variant">Unit:</dt>
                        <dd class="col-span-2 text-on-surface">{{ $config['sample_rows'][0]['unit'] ?? 'Source-defined unit' }}</dd>
                        <dt class="text-on-surface-variant">Period:</dt>
                        <dd class="col-span-2 font-mono text-on-surface">{{ $config['sample_rows'][0]['period'] ?? 'Source-defined period' }}</dd>
                        <dt class="text-on-surface-variant">Version:</dt>
                        <dd class="col-span-2 text-on-surface">{{ $latestVersion?->version_label ?? 'No published version' }}</dd>
                        <dt class="text-on-surface-variant">Coverage:</dt>
                        <dd class="col-span-2 text-on-surface">Partial coverage and no-value states remain visible.</dd>
                    </dl>
                    <p class="mt-4 border-t border-outline-variant pt-3 text-xs leading-5 text-on-surface-variant"><strong>Missingness Note:</strong> Empty, unavailable, and no-value source states must render as labelled states, not zero values.</p>
                </section>

                <section class="rounded-lg border border-outline-variant bg-surface-container-lowest p-4">
                    <h2 class="font-headline text-xl font-semibold text-primary">Dashboard states</h2>
                    <div class="mt-3 grid gap-2">
                        @foreach ($config['modules'] as $module)
                            <div class="rounded border border-outline-variant bg-surface p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant">{{ $module['type'] }}</p>
                                <h3 class="mt-1 font-semibold text-primary">{{ $module['title'] }}</h3>
                                <p class="mt-1 text-xs leading-5 text-on-surface-variant">{{ $module['status'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            </aside>
            @endif
        </section>

        @if ($theme === 'emissions' && ! $isClimateTraceMode)
            <section class="mt-5 overflow-hidden rounded-lg border border-outline-variant bg-surface-container-lowest">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant bg-surface-bright p-4">
                    <div>
                        <h2 class="font-headline text-xl font-semibold text-primary">Available country comparison</h2>
                        <p class="mt-1 text-sm text-on-surface-variant">Latest available development fixture totals for the selected period and sector.</p>
                    </div>
                    <a class="ch-btn-secondary" href="/datasets/climatewatch_historical_emissions">Dataset details</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] ch-data-table">
                        <thead><tr><th>Country</th><th>Latest year</th><th>Selected total</th><th>Source</th></tr></thead>
                        <tbody>
                            <template x-for="country in latestTotals" :key="country.country">
                                <tr><td class="font-semibold" x-text="country.country"></td><td x-text="latestYear"></td><td x-text="formatNumber(country.value)"></td><td>Climate Watch · development sample</td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </section>
            </div>
        @else
        <section class="mt-5 overflow-hidden rounded-lg border border-outline-variant bg-surface-container-lowest">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant bg-surface-bright p-4">
                <h2 class="font-headline text-xl font-semibold text-primary">Raw Data Extraction</h2>
                <div class="flex flex-wrap gap-2">
                    <a class="ch-btn-secondary" href="/explore"><span class="material-symbols-outlined text-[18px]">table_view</span>Toggle Table View</a>
                    <a class="ch-btn-primary" href="/downloads"><span class="material-symbols-outlined text-[18px]">download</span>Download CSV</a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] ch-data-table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Period</th>
                            <th>Unit</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($config['sample_rows'] as $row)
                            <tr>
                                <td class="font-semibold">{{ $row['label'] }}</td>
                                <td>{{ $row['period'] }}</td>
                                <td>{{ $row['unit'] }}</td>
                                <td><span class="rounded bg-[#e6f4ea] px-2 py-1 text-xs font-semibold text-[#137333]">{{ $row['state'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @endif

        @if ($theme !== 'emissions')
        <section class="mt-8">
            <div class="mb-4 border-b border-outline-variant pb-1">
                <h2 class="font-headline text-xl font-semibold text-primary">Open the source records behind this workspace.</h2>
            </div>
            @include('public.partials.dataset-list', ['datasets' => $datasets])
        </section>
        @endif
    </main>
@endsection

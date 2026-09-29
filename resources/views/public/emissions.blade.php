@extends('layouts.dataset-browser', [
    'title' => 'Global emissions',
    'activeNav' => 'themes',
])

@section('content')
    <main class="ch-shell emissions-page pb-10 pt-0" x-data="emissionsComparison(@js($emissionsDashboard['comparison_data']))">
        <section class="emissions-hero emissions-bleed">
            <div class="ch-shell py-6 md:py-7">
                <div class="flex items-center gap-3 text-xs font-medium text-on-surface-variant">
                <a class="text-[#045A58] hover:underline" href="{{ route('topics.index') }}">Topics</a>
                <span class="material-symbols-outlined text-[14px]" aria-hidden="true">chevron_right</span>
                <span>Emissions</span>
                </div>
                <div class="mt-4">
                    <div>
                        <h1 class="font-headline text-3xl font-semibold text-[#045A58] md:text-4xl">Global emissions</h1>
                        <p class="mt-3 max-w-3xl text-sm leading-6 text-on-surface-variant">Compare published greenhouse-gas records by country, sector, gas, and reporting year.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="emissions-controls emissions-bleed" aria-label="Emissions controls">
            <div class="ch-shell py-5 md:py-6">
            <fieldset>
                <div class="flex gap-1 overflow-x-auto border-b border-outline-variant" role="tablist" aria-label="Chart view">
                    <template x-for="mode in viewModes" :key="mode.code"><button type="button" class="min-h-10 shrink-0 border-b-2 px-4 text-sm font-semibold transition-colors" :class="viewMode === mode.code ? 'border-[#045A58] text-[#045A58]' : 'border-transparent text-on-surface-variant hover:border-[#E7B460] hover:text-[#045A58]'" role="tab" :aria-selected="viewMode === mode.code" @click="setView(mode.code)" x-text="mode.label"></button></template>
                </div>
            </fieldset>
            <p class="border-b border-[#BFD5D0] py-3 text-xs leading-5 text-on-surface-variant" x-text="filterNote"></p>
            <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-12 lg:items-end">

                <div x-show="viewMode === 'trends'" class="relative lg:col-span-3" @click.outside="countryMenuOpen = false">
                    <span class="block text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant">Countries</span>
                    <button class="ch-input mt-2 flex w-full items-center justify-between py-2 text-left" type="button" @click="countryMenuOpen = ! countryMenuOpen" :aria-expanded="countryMenuOpen">
                        <span x-text="countrySelectionLabel"></span>
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">expand_more</span>
                    </button>
                    <div x-cloak x-show="countryMenuOpen" class="absolute left-0 top-full z-50 mt-2 w-full border border-[#BFD5D0] bg-white p-3 shadow-xl">
                        <input class="ch-input w-full py-2 text-sm" type="search" x-model="countrySearch" placeholder="Search countries">
                        <div class="mt-2 flex items-center justify-between gap-2 border-b border-outline-variant pb-2 text-xs font-semibold text-secondary"><button type="button" @click="selectAllCountries()">Select first 6</button><span x-show="viewMode === 'trends'" x-text="trendCountryLimitLabel"></span><button type="button" @click="selectedCountries = []">Clear</button></div>
                        <div class="mt-2 max-h-56 space-y-1 overflow-auto">
                            <template x-for="country in visibleCountries" :key="country.code">
                                <label class="flex cursor-pointer items-center gap-2 px-1 py-1.5 text-sm hover:bg-surface-container">
                                    <input type="checkbox" class="rounded border-outline-variant text-secondary" :value="country.code" x-model="selectedCountries" @change="limitCountrySelection($event, country.code)">
                                    <span x-text="country.label"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </div>

                <label x-show="viewMode === 'sectors' || viewMode === 'composition'" class="lg:col-span-2">
                    <span class="block text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant">Country</span>
                    <select class="ch-input mt-2 w-full py-2" x-model="selectedSectorCountry" x-effect="$el.value = selectedSectorCountry"><template x-for="country in countries" :key="country.code"><option :value="country.code" :selected="country.code === selectedSectorCountry" x-text="country.label"></option></template></select>
                </label>

                <label x-show="viewMode !== 'composition'" class="lg:col-span-3" :class="viewMode === 'map' ? 'lg:col-span-4' : 'lg:col-span-3'">
                    <span class="block text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant">Gas</span>
                    <div class="mt-2">
                        <select class="ch-input py-2" x-model="selectedGas" x-effect="$el.value = selectedGas">
                            <template x-for="gas in gases" :key="gas.code"><option :value="gas.code" :selected="gas.code === selectedGas" x-text="gas.label"></option></template>
                        </select>
                    </div>
                </label>

                <div x-show="viewMode === 'composition'" class="relative lg:col-span-3" @click.outside="compositionGasMenuOpen = false">
                    <span class="block text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant">Gases</span>
                    <button class="ch-input mt-2 flex w-full items-center justify-between py-2 text-left" type="button" @click="compositionGasMenuOpen = ! compositionGasMenuOpen"><span x-text="compositionGasSelectionLabel"></span><span class="material-symbols-outlined text-[18px]" aria-hidden="true">expand_more</span></button>
                    <div x-cloak x-show="compositionGasMenuOpen" class="absolute left-0 top-full z-50 mt-2 w-full border border-[#BFD5D0] bg-white p-3 shadow-xl">
                        <div class="flex gap-2 border-b border-outline-variant pb-2 text-xs font-semibold text-secondary"><button type="button" @click="selectAllCompositionGases()">Select all</button><button type="button" @click="selectedCompositionGases = []">Clear</button></div>
                        <div class="mt-2 max-h-56 space-y-1 overflow-auto"><template x-for="gas in gases" :key="gas.code"><label class="flex cursor-pointer items-center gap-2 px-1 py-1.5 text-sm hover:bg-surface-container"><input type="checkbox" class="rounded border-outline-variant text-secondary" :value="gas.code" x-model="selectedCompositionGases" @change="normalizeCompositionGasSelection(gas.code)"><span x-text="gas.label"></span></label></template></div>
                    </div>
                </div>

                <label x-show="viewMode !== 'sectors' && viewMode !== 'countries'" class="lg:col-span-3" :class="viewMode === 'map' ? 'lg:col-span-4' : 'lg:col-span-3'">
                    <span class="block text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant">Sector</span>
                    <select class="ch-input mt-2 w-full py-2 disabled:cursor-not-allowed disabled:opacity-60" x-model="selectedSector" :disabled="viewMode === 'sectors'">
                        <template x-for="sector in sectors" :key="sector.code"><option :value="sector.code" x-text="sector.label"></option></template>
                    </select>
                </label>

                <div x-show="viewMode === 'map' || viewMode === 'trends' || viewMode === 'composition' || viewMode === 'sectors' || viewMode === 'countries'" class="lg:col-span-3" :class="viewMode === 'map' ? 'lg:col-span-4' : 'lg:col-span-3'">
                    <span class="block text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant">Reporting years</span>
                    <div class="mt-2 grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2">
                        <select class="ch-input min-w-0 py-2" x-model.number="yearStartIndex"><template x-for="(year, index) in availableYears" :key="year"><option :value="index" x-text="year"></option></template></select>
                        <span class="text-on-surface-variant">to</span>
                        <select class="ch-input min-w-0 py-2" x-model.number="yearEndIndex"><template x-for="(year, index) in availableYears" :key="year"><option :value="index" x-text="year"></option></template></select>
                    </div>
                </div>
            </div>
        </section>

        <section class="emissions-visual-stage emissions-bleed" aria-live="polite">
            <div class="bg-[#045A58] text-white">
                <div class="ch-shell flex flex-wrap items-center justify-between gap-3 py-4">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined mt-0.5 text-[22px] text-[#E7B460]" aria-hidden="true" x-text="chartIcon"></span>
                        <div>
                            <h2 class="font-headline text-lg font-semibold" x-text="chartTitle"></h2>
                            <p class="mt-1 text-xs text-white/75" x-text="chartSubtitle"></p>
                        </div>
                    </div>
                    <span class="border border-[#E7B460]/70 px-3 py-1 text-xs font-semibold text-[#FFF3D4]" x-text="chartYearLabel"></span>
                </div>
            </div>
            <div class="bg-[#F4F8F7]">
                <div class="ch-shell">
                    <div class="emissions-analysis-grid" :class="{ 'emissions-analysis-grid--with-rail': viewMode === 'map' }">
                        <div class="relative overflow-hidden p-5 md:p-6" :class="{ 'p-0': viewMode === 'countries' }">
                    <div x-show="viewMode === 'countries'" class="emissions-country-matrix">
                        <div class="emissions-country-matrix__scroll" role="region" aria-label="Published country emissions by sector" tabindex="0">
                            <table class="emissions-country-matrix__table">
                                <thead>
                                    <tr>
                                        <th class="emissions-country-matrix__country" :aria-sort="countryMatrixSortState('country')"><button type="button" @click="sortCountryMatrix('country')">Country <i class="material-symbols-outlined" aria-hidden="true" x-text="countryMatrixSortIcon('country')"></i></button></th>
                                        <template x-for="sector in countryMatrixSectors" :key="sector.code"><th :aria-sort="countryMatrixSortState(sector.code)"><button type="button" @click="sortCountryMatrix(sector.code)"><b x-text="sector.label"></b><i class="material-symbols-outlined" aria-hidden="true" x-text="countryMatrixSortIcon(sector.code)"></i></button></th></template>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="row in countryMatrixRows" :key="row.code">
                                        <tr>
                                            <th class="emissions-country-matrix__country"><button type="button" @click="openMatrixCountryTrend(row.code)" x-text="row.label"></button></th>
                                            <template x-for="sector in countryMatrixSectors" :key="`${row.code}-${sector.code}`"><td :class="row.values[sector.code] === null ? 'text-on-surface-variant' : ''" x-text="row.values[sector.code] === null ? '—' : formatNumber(row.values[sector.code])"></td></template>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div x-show="viewMode !== 'countries'" class="relative h-[320px] md:h-[420px]">
                    <div class="absolute inset-x-0 top-0 z-20 flex flex-wrap gap-x-4 gap-y-2 text-xs text-on-surface-variant" x-show="viewMode === 'trends'">
                        <template x-for="series in chartSeries" :key="series.code"><button type="button" class="flex items-center gap-2 rounded px-1 py-1 text-left transition-colors hover:bg-surface-container" :class="highlightedCountry === series.code ? 'bg-surface-container font-semibold text-primary' : ''" @click="toggleCountry(series.code)" :aria-pressed="highlightedCountry === series.code"><span class="h-2.5 w-2.5 rounded-full" :style="`background-color: ${series.color}`"></span><span x-text="series.label"></span></button></template>
                    </div>
                    <div class="absolute inset-x-0 top-0 z-20 flex flex-wrap gap-x-4 gap-y-2 text-xs text-on-surface-variant" x-show="viewMode === 'composition'">
                        <template x-for="series in compositionSeries" :key="series.code"><span class="flex items-center gap-2 rounded bg-surface-container-lowest px-2 py-1"><span class="h-2.5 w-2.5 rounded-full" :style="`background-color: ${series.color}`"></span><span x-text="series.label"></span></span></template>
                    </div>
                    <div x-show="viewMode === 'map'" class="relative z-10 h-full">
                        <svg class="block h-[calc(100%-2.25rem)] w-full" viewBox="0 0 1000 500" role="img" :aria-label="chartTitle" @click="selectMapCountryFromEvent($event)"><g x-html="mapMarkup"></g></svg>
                        <div class="absolute inset-x-0 bottom-0 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-on-surface-variant"><span class="flex items-center gap-2"><span class="h-3 w-3 border border-outline-variant bg-[#D9E2DF]"></span>No published value</span><span class="flex items-center gap-2"><span class="h-3 w-3 bg-[#A8D4CD]"></span>Lower published value</span><span class="flex items-center gap-2"><span class="h-3 w-3 bg-[#045A58]"></span>Higher published value</span><span class="flex items-center gap-2"><span class="h-3 w-3 border border-[#A87B29] bg-[#E7B460]"></span>Selected country</span></div>
                        <p class="absolute bottom-0 left-0 text-xs text-on-surface-variant" x-show="mapLoading">Loading global boundaries.</p>
                        <p class="absolute bottom-0 left-0 text-xs text-error" x-show="mapError" x-text="mapError"></p>
                    </div>
                    <canvas x-show="!['map', 'countries'].includes(viewMode)" x-ref="chart" class="absolute z-10 block w-full cursor-crosshair" :class="['trends', 'composition'].includes(viewMode) ? 'bottom-0 h-[calc(100%-2.25rem)]' : 'inset-0 h-full'" width="980" height="380" role="img" :aria-label="chartTitle" @mousemove="handleChartHover($event)" @mouseleave="hideTooltip()"></canvas>
                    <div x-cloak x-show="tooltip.visible" class="pointer-events-none absolute z-10 rounded border border-outline-variant bg-surface-container-lowest px-3 py-2 text-xs shadow-lg" :style="`left:${tooltip.x}px; top:${tooltip.y}px; transform: translate(12px, -110%);`">
                        <p class="font-semibold text-primary" x-text="tooltip.label"></p><p class="mt-1 text-on-surface-variant"><span x-text="tooltip.year"></span> · <span x-text="formatNumber(tooltip.value)"></span></p>
                    </div>
                </div>
                    <p class="mt-3 text-xs text-on-surface-variant" x-show="recordsLoading">Updating published values.</p>
                    <p class="mt-3 text-xs text-error" x-show="recordsError" x-text="recordsError"></p>
                    <p class="mt-3 text-xs text-on-surface-variant" x-show="!recordsLoading && !recordsError && !hasChartData">No published records match these controls.</p>
                </div>
                        <aside class="emissions-insight-rail" x-cloak x-show="viewMode === 'map'">
                    <div x-show="viewMode === 'map' && selectedMapRow">
                        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#A87B29]">Selected country</p>
                        <h3 class="mt-2 font-headline text-2xl font-semibold text-[#045A58]" x-text="profileCountryLabel"></h3>
                        <dl class="mt-6 space-y-4">
                            <div class="border-b border-[#D7E4E0] pb-3"><dt class="text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant">Published value</dt><dd class="mt-1 text-2xl font-semibold text-[#045A58]" x-text="formatNumber(profile?.total || 0)"></dd><dd class="mt-1 text-xs text-on-surface-variant"><span x-text="profile?.year"></span> <span x-text="profilePeriodLabel"></span></dd></div>
                            <div class="border-b border-[#D7E4E0] pb-3"><dt class="text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant">Global rank</dt><dd class="mt-1 text-2xl font-semibold text-[#045A58]">#<span x-text="selectedMapRow?.rank"></span></dd></div>
                            <div><dt class="text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant">Published source</dt><dd class="mt-1 text-sm font-medium text-on-surface">{{ $emissionsDashboard['source_name'] ?? 'Not available' }}</dd></div>
                        </dl>
                        <button type="button" class="mt-6 inline-flex items-center gap-2 border border-[#045A58] px-3 py-2 text-sm font-semibold text-[#045A58] hover:bg-[#045A58] hover:text-white" @click="openCountryTrend()">View country trend <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span></button>
                    </div>
                        </aside>
                    </div>
                </div>
            </div>
        </section>

        <section class="emissions-context mt-5 grid gap-px overflow-hidden border border-[#BFD5D0] bg-[#BFD5D0] lg:grid-cols-3" x-show="profile && viewMode === 'trends'">
            <article class="bg-white p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant"><span x-text="profileCountryLabel"></span> latest emissions</p>
                <p class="mt-2 font-headline text-3xl font-semibold text-[#045A58]"><span x-text="formatNumber(profile?.total || 0)"></span></p>
                <p class="mt-1 text-sm text-on-surface-variant"><span x-text="profile?.year"></span> reporting year</p>
            </article>
            <article class="bg-white p-5" x-show="viewMode === 'map' && selectedMapRow">
                <p class="text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant"><span x-text="profileCountryLabel"></span> rank</p>
                <p class="mt-2 font-headline text-3xl font-semibold text-[#045A58]">#<span x-text="selectedMapRow?.rank"></span></p>
                <button type="button" class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-[#A87B29] hover:text-[#045A58]" @click="openCountryTrend()">View country trend <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span></button>
            </article>
            <article class="bg-white p-5" x-show="viewMode !== 'map'">
                <p class="text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant">Largest <span x-text="profileCountryLabel"></span> sector</p>
                <p class="mt-2 font-headline text-xl font-semibold text-[#045A58]" x-text="profile?.largestSector || 'No matching sector'"></p>
                <p class="mt-1 text-sm text-on-surface-variant"><span x-text="formatNumber(profile?.largestValue || 0)"></span> in the selected gas</p>
            </article>
            <article class="bg-white p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant">Last data update</p>
                <p class="mt-2 font-headline text-xl font-semibold text-[#045A58]">{{ $emissionsDashboard['last_updated_label'] ?? 'Not available' }}</p>
                <p class="mt-1 text-sm text-on-surface-variant">Source: {{ $emissionsDashboard['source_name'] ?? 'Not available' }}</p>
            </article>
        </section>

        <section class="emissions-results mt-5 overflow-hidden border border-[#BFD5D0] bg-white" x-show="!['countries', 'sectors'].includes(viewMode)">
            <div class="flex items-center justify-between gap-3 border-b border-[#BFD5D0] px-5 py-4">
                <div><p class="text-xs font-semibold uppercase tracking-[0.05em] text-[#A87B29]">Published records</p><h2 class="mt-1 font-headline text-lg font-semibold text-[#045A58]" x-text="tableTitle"></h2><p class="mt-1 text-xs text-on-surface-variant" x-text="tableSubtitle"></p></div>
                <span class="border border-[#BFD5D0] px-2 py-1 text-xs font-semibold text-[#045A58]" x-text="`${tableRowCount} rows`"></span>
            </div>
            <div x-show="viewMode === 'trends'" class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-surface-container-low text-xs uppercase tracking-[0.05em] text-on-surface-variant"><tr><th class="sticky left-0 bg-surface-container-low px-5 py-3">Country</th><template x-for="year in selectedYearRange" :key="year"><th class="px-5 py-3 text-right" x-text="year"></th></template></tr></thead><tbody><template x-for="series in chartSeries" :key="series.code"><tr class="border-t border-outline-variant"><td class="sticky left-0 bg-surface-container-lowest px-5 py-3 font-medium text-primary" x-text="series.label"></td><template x-for="(value, index) in series.values" :key="`${series.code}-${selectedYearRange[index]}`"><td class="px-5 py-3 text-right" x-text="formatNumber(value)"></td></template></tr></template></tbody></table></div>
            <div x-show="viewMode !== 'trends'" class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-surface-container-low text-xs uppercase tracking-[0.05em] text-on-surface-variant"><tr><th class="px-5 py-3" x-text="tableLabel"></th><th class="px-5 py-3 text-right">Value</th><th class="px-5 py-3 text-right" x-text="viewMode === 'composition' ? 'Period' : 'Year'"></th></tr></thead><tbody><template x-for="row in tableRows" :key="`${row.label}-${row.year}`"><tr class="border-t border-outline-variant"><td class="px-5 py-3 font-medium text-primary" x-text="row.label"></td><td class="px-5 py-3 text-right" x-text="formatNumber(row.value)"></td><td class="px-5 py-3 text-right text-on-surface-variant" x-text="row.year"></td></tr></template></tbody></table></div>
        </section>

        <section class="emissions-source-strip mt-5">
            <div><p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#A87B29]">Published source</p><p class="mt-1 text-sm font-medium text-[#045A58]">{{ $emissionsDashboard['source_name'] ?? 'Not available' }}</p></div>
            <div><p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#A87B29]">Last update</p><p class="mt-1 text-sm font-medium text-[#045A58]">{{ $emissionsDashboard['last_updated_label'] ?? 'Not available' }}</p></div>
            <p class="text-sm leading-6 text-on-surface-variant">Kyoto GHG is displayed only where it is supplied by the published source as an aggregate series.</p>
        </section>
    </main>
@endsection

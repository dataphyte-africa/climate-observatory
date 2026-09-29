@extends('layouts.dataset-browser', ['title' => ($isSearchPage ?? false) ? 'Search datasets' : 'Explore datasets', 'activeNav' => 'explore'])

@section('content')
    <div x-cloak x-data="datasetExplorer()">
        <div class="ch-shell py-6">
            <section class="min-w-0">
                <div class="mb-6">
                    <p class="text-sm font-semibold uppercase tracking-[0.08em] text-secondary">{{ ($isSearchPage ?? false) ? 'Search climate data' : 'Nigeria climate data' }}</p>
                    <h1 class="mt-2 font-headline text-2xl font-semibold leading-8 text-primary md:text-4xl md:leading-10">{{ ($isSearchPage ?? false) ? 'Search datasets' : 'Explore datasets, trends, policy records, and impact reports in one place.' }}</h1>
                    <p class="mt-3 max-w-3xl text-base leading-7 text-on-surface-variant">{{ ($isSearchPage ?? false) ? 'Search dataset titles, sources, and descriptions, then refine the matching catalogue.' : 'Open any dataset to review source, period, version, available downloads, and exact rows.' }}</p>
                    @if ($isSearchPage ?? false)
                        <form class="mt-5 flex flex-col gap-3 sm:flex-row" action="{{ route('search') }}" method="get">
                            <label class="min-w-0 flex-1">
                                <span class="sr-only">Search datasets</span>
                                <input class="ch-input" type="search" name="q" :value="catalogFilters.query" placeholder="Search titles, sources, or descriptions" autofocus>
                            </label>
                            <button class="ch-btn-primary shrink-0" type="submit">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">search</span>
                                Search
                            </button>
                        </form>
                    @endif
                    <p x-show="catalogFilters.query" class="mt-3 text-sm text-on-surface-variant">Search results for <span class="font-semibold text-primary" x-text="catalogFilters.query"></span>.</p>
                </div>

                <section class="ch-card mb-6 p-4" aria-labelledby="catalogue-filters-title">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                        <div>
                            <h2 id="catalogue-filters-title" class="font-headline text-lg font-semibold text-primary">Filter the catalogue</h2>
                            <p class="mt-1 text-sm text-on-surface-variant">Narrow the datasets shown below by family or source.</p>
                        </div>
                        <p class="text-sm text-on-surface-variant"><span class="font-semibold text-primary" x-text="filteredDatasets().length"></span> matching datasets</p>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end">
                        <label class="space-y-2">
                            <span class="text-sm font-semibold text-on-surface">Dataset family</span>
                            <select x-model="catalogFilters.dataset_type" class="ch-input">
                                <option value="">All families</option>
                                <template x-for="item in lookupOptions('dataset_types')" :key="item.code">
                                    <option :value="item.code" x-text="item.name"></option>
                                </template>
                            </select>
                        </label>
                        <label class="space-y-2">
                            <span class="text-sm font-semibold text-on-surface">Source</span>
                            <select x-model="catalogFilters.source_code" class="ch-input">
                                <option value="">All sources</option>
                                <template x-for="item in lookupOptions('sources')" :key="item.code">
                                    <option :value="item.code" x-text="item.name"></option>
                                </template>
                            </select>
                        </label>
                        <button class="ch-btn-primary w-full md:w-auto" type="button">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">filter_list</span>
                            Apply Filters
                        </button>
                    </div>
                </section>

                <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="ch-card px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.08em] text-on-surface-variant">Matching datasets</p>
                        <p class="mt-2 font-headline text-3xl font-semibold leading-none text-primary" x-text="filteredDatasets().length"></p>
                    </div>
                    <div class="ch-card px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.08em] text-on-surface-variant">Available records</p>
                        <p class="mt-2 font-headline text-3xl font-semibold leading-none text-primary" x-text="totalSeededRows()"></p>
                    </div>
                    <div class="ch-card px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.08em] text-on-surface-variant">Map binding</p>
                        <p class="mt-2 text-sm font-semibold text-primary">Provisional</p>
                    </div>
                    <div class="ch-card px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.08em] text-on-surface-variant">Raw files</p>
                        <p class="mt-2 text-sm font-semibold text-primary">Not exposed</p>
                    </div>
                </div>

                <div x-show="loadingCatalog" class="ch-card p-8">
                    <div class="grid gap-3">
                        <div class="h-5 w-48 rounded skeleton-loader"></div>
                        <div class="h-4 w-full rounded skeleton-loader"></div>
                        <div class="h-4 w-3/4 rounded skeleton-loader"></div>
                    </div>
                    <p class="mt-5 text-sm text-on-surface-variant">Loading datasets...</p>
                </div>

                <div x-show="!loadingCatalog && filteredDatasets().length === 0" class="ch-card p-10 text-center text-on-surface-variant">
                    No datasets match the current filters.
                </div>

                <div class="ch-card overflow-hidden" x-show="!loadingCatalog && filteredDatasets().length > 0">
                    <div class="border-b border-outline-variant bg-surface-bright p-4">
                        <h2 class="font-headline text-xl font-semibold text-primary">Dataset catalogue</h2>
                        <p class="mt-1 text-sm text-on-surface-variant">Structured source records prepared for public analysis.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[920px] ch-data-table">
                            <thead>
                                <tr>
                                    <th>Dataset</th>
                                    <th>Family</th>
                                    <th>Source</th>
                                    <th>Version</th>
                                    <th>Rows</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="dataset in filteredDatasets()" :key="dataset.code">
                                    <tr>
                                        <td>
                                            <div class="font-semibold text-primary" x-text="dataset.title"></div>
                                            <div class="mt-1 max-w-md text-xs leading-5 text-on-surface-variant" x-text="dataset.summary"></div>
                                        </td>
                                        <td><span class="ch-chip" x-text="datasetTypeLabel(dataset.dataset_type)"></span></td>
                                        <td x-text="dataset.source?.name || 'Unassigned source'"></td>
                                        <td x-text="dataset.latest_version?.label || 'None'"></td>
                                        <td x-text="dataset.latest_version?.row_count ?? 0"></td>
                                        <td class="capitalize" x-text="dataset.status"></td>
                                        <td><a :href="`/datasets/${dataset.code}`" class="font-semibold text-secondary">Open</a></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

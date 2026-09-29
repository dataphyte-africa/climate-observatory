@extends('layouts.dataset-browser', ['title' => 'Dataset', 'activeNav' => 'datasets'])

@php
    $settings = $dataset->settings ?? [];
    $caveats = collect($settings['caveats'] ?? [])->filter()->values();
    $glossary = collect($settings['glossary'] ?? [])->filter()->all();
    $sourceNote = $dataset->source?->metadata['editorial_note'] ?? null;
    $latestVersion = $dataset->versions->first();
@endphp

@section('content')
    <div x-cloak x-data="datasetExplorer({ datasetCode: @js($datasetCode) })">
        <div class="ch-shell flex w-full py-6">
            <aside class="sticky top-20 hidden h-fit w-56 shrink-0 flex-col rounded-lg border border-outline-variant bg-surface-container-low p-4 lg:mr-6 lg:flex">
                <div class="border-b border-outline-variant pb-4">
                    <h2 class="font-headline text-xl font-semibold text-primary">Topics</h2>
                    <p class="mt-1 text-sm text-on-surface-variant">Data categories</p>
                </div>
                <nav class="mt-4 flex flex-col gap-2">
                    @foreach ([
                        ['label' => 'Emissions', 'icon' => 'factory', 'href' => '/emissions'],
                        ['label' => 'Rainfall', 'icon' => 'cloudy_snowing', 'href' => '/rainfall'],
                        ['label' => 'Floods', 'icon' => 'flood', 'href' => '/floods'],
                        ['label' => 'Risk', 'icon' => 'warning', 'href' => '/risk'],
                        ['label' => 'Policy', 'icon' => 'policy', 'href' => '/policy'],
                        ['label' => 'Finance', 'icon' => 'payments', 'href' => '/finance'],
                        ['label' => 'Agriculture', 'icon' => 'eco', 'href' => '/agriculture'],
                    ] as $topic)
                        <a class="flex items-center gap-3 rounded-lg p-3 text-sm text-on-surface-variant transition hover:bg-surface-container-high" href="{{ $topic['href'] }}">
                            <span class="material-symbols-outlined" aria-hidden="true">{{ $topic['icon'] }}</span>
                            <span>{{ $topic['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </aside>

            <div class="min-w-0 flex-1">
                <a class="mb-6 inline-flex items-center gap-2 text-sm font-medium text-primary transition hover:text-primary-container" href="/datasets">
                    <span class="material-symbols-outlined text-base">arrow_back</span>
                    Back to datasets
                </a>

                <section class="mb-6" x-show="dataset">
                    <div class="flex items-center gap-2 text-sm text-on-surface-variant">
                        <a class="hover:text-secondary" href="/explore">Explore data</a>
                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">chevron_right</span>
                        <span x-text="datasetTypeLabel(dataset?.dataset_type)"></span>
                    </div>
                    <h1 class="mt-2 font-headline text-2xl font-semibold leading-8 text-primary md:text-4xl md:leading-10" x-text="dataset?.title"></h1>
                    <p class="mt-3 max-w-3xl text-base leading-7 text-on-surface-variant" x-text="dataset?.summary"></p>
                    <div class="mt-5 flex gap-8 border-b border-outline-variant text-sm font-semibold uppercase tracking-[0.08em]">
                        <a class="border-b-2 border-transparent pb-3 text-on-surface-variant" href="#overview">Overview</a>
                        <a class="border-b-2 border-primary-container pb-3 text-primary-container" href="#visualise">Visualise</a>
                        <a class="border-b-2 border-transparent pb-3 text-on-surface-variant" href="#data">Data</a>
                        <a class="border-b-2 border-transparent pb-3 text-on-surface-variant" href="#metadata">Metadata</a>
                    </div>
                </section>

                <div id="visualise" class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    <section class="ch-card h-[500px] overflow-hidden lg:col-span-8">
                        <div class="ch-card-header">
                            <h2 class="font-headline text-base font-semibold">Spatial Distribution</h2>
                            <span class="text-xs font-semibold">Loading/Error States</span>
                        </div>
                        <div class="relative flex h-[calc(100%-48px)] items-center justify-center overflow-hidden bg-surface-container-low">
                            <div class="absolute inset-0 grid grid-cols-4 grid-rows-4 gap-1 p-1 opacity-25">
                                @for ($i = 0; $i < 16; $i++)
                                    <div class="rounded skeleton-loader"></div>
                                @endfor
                            </div>
                            <div class="relative z-10 rounded-lg border border-outline-variant bg-surface-container-lowest/90 p-6 text-center shadow-sm">
                                <span class="material-symbols-outlined mb-2 text-[48px] text-outline">map</span>
                                <p class="font-semibold text-primary">Loading geographic layers...</p>
                                <p class="mt-2 max-w-sm text-sm leading-6 text-on-surface-variant">Map and chart modules will bind only after public geometry and endpoint evidence are approved.</p>
                            </div>
                        </div>
                    </section>

                    <aside id="metadata" class="ch-card flex flex-col lg:col-span-4">
                        <div class="border-b border-outline-variant p-4">
                            <h2 class="font-headline text-xl font-semibold text-primary">Dataset Metadata</h2>
                            <p class="mt-2 text-xs font-semibold uppercase tracking-[0.08em] text-on-surface-variant">Source and citation</p>
                            <p class="mt-1 text-sm text-on-surface-variant">Source, version, coverage, and reuse context.</p>
                        </div>
                        <dl class="grid grid-cols-3 gap-x-3 gap-y-4 p-4 text-sm">
                            <dt class="text-on-surface-variant">Source:</dt>
                            <dd class="col-span-2 font-semibold text-on-surface">{{ $dataset->source?->name ?? 'Unassigned source' }}</dd>
                            @if ($dataset->source?->license_name)
                                <dt class="text-on-surface-variant">Licence:</dt>
                                <dd class="col-span-2 text-on-surface">{{ $dataset->source->license_name }}</dd>
                            @endif
                            <dt class="text-on-surface-variant">Version:</dt>
                            <dd class="col-span-2 text-on-surface">{{ $latestVersion?->version_label ?? 'Not published' }}</dd>
                            <dt class="text-on-surface-variant">Available view:</dt>
                            <dd class="col-span-2 text-on-surface" x-text="datasetViewLabel(dataset?.dataset_type)"></dd>
                            <dt class="text-on-surface-variant">Rows:</dt>
                            <dd class="col-span-2 font-mono text-on-surface">{{ number_format($latestVersion?->row_count ?? 0) }}</dd>
                        </dl>
                        @if ($sourceNote || $dataset->source?->citation_template)
                            <div class="mx-4 mb-4 border-t border-outline-variant pt-4 text-xs leading-5 text-on-surface-variant">
                                @if ($sourceNote)
                                    <p>{{ $sourceNote }}</p>
                                @endif
                                @if ($dataset->source?->citation_template)
                                    <p class="mt-2"><strong class="text-on-surface">Citation:</strong> {{ $dataset->source->citation_template }}</p>
                                @endif
                            </div>
                        @endif
                    </aside>
                </div>

                <section id="overview" class="mt-6 grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                    <div class="ch-card p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-on-surface-variant">Coverage and caveats</p>
                        @if ($dataset->description)
                            <p class="mt-3 text-sm leading-6 text-on-surface-variant">{{ $dataset->description }}</p>
                        @endif
                        @if (! empty($settings['coverage_note']))
                            <div class="mt-4 rounded bg-surface-container-low px-4 py-4 text-sm leading-6 text-on-surface-variant">
                                <strong class="text-on-surface">Coverage:</strong> {{ $settings['coverage_note'] }}
                            </div>
                        @endif
                        @if ($caveats->isNotEmpty())
                            <ul class="mt-4 space-y-2 text-sm leading-6 text-on-surface-variant">
                                @foreach ($caveats as $caveat)
                                    <li class="flex gap-2">
                                        <span class="material-symbols-outlined mt-0.5 text-base text-tertiary" aria-hidden="true">warning</span>
                                        <span>{{ $caveat }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="ch-card p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-on-surface-variant">About this dataset</p>
                        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs uppercase tracking-[0.08em] text-on-surface-variant">Dataset code</dt>
                                <dd class="mt-1 break-all text-sm font-semibold text-on-surface" x-text="dataset?.code"></dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-[0.08em] text-on-surface-variant">Dataset type</dt>
                                <dd class="mt-1 text-sm font-semibold text-on-surface" x-text="datasetTypeLabel(dataset?.dataset_type)"></dd>
                            </div>
                        </dl>
                        <div class="mt-5 rounded bg-surface-container-low px-4 py-4 text-sm leading-6 text-on-surface-variant">
                            <p><strong class="text-on-surface">Source and method:</strong> Use the record table for exact values. Chart and map modules will show source, unit, period, version, methodology, and missingness labels once API and MAP handoffs are finalized.</p>
                        </div>
                    </div>
                </section>

                @if (! empty($glossary))
                    <section class="ch-card mt-6 p-5">
                        <div class="mb-5">
                            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-on-surface-variant">Indicator labels</p>
                            <h2 class="mt-2 font-headline text-xl font-semibold text-primary">Plain-language labels for source codes</h2>
                        </div>
                        <dl class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($glossary as $code => $label)
                                <div class="rounded border border-outline-variant bg-surface p-4">
                                    <dt class="font-mono text-xs font-semibold uppercase text-on-surface-variant">{{ $code }}</dt>
                                    <dd class="mt-2 text-sm leading-6 text-on-surface">{{ $label }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endif

                <section class="ch-card mt-6 p-4">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5" x-show="dataset">
                    <template x-for="field in activeFilterFields()" :key="field.key">
                        <label class="space-y-2">
                            <span class="text-sm font-medium text-on-surface-variant" x-text="field.label"></span>

                            <template x-if="field.type === 'select'">
                                <select x-model="filters[field.key]" class="ch-input">
                                    <option value="">All</option>
                                    <template x-for="item in lookupOptions(field.lookup)" :key="item.code">
                                        <option :value="item.code" x-text="item.name"></option>
                                    </template>
                                </select>
                            </template>

                            <template x-if="field.type === 'text'">
                                <input x-model="filters[field.key]" class="ch-input" type="text">
                            </template>

                            <template x-if="field.type === 'number'">
                                <input x-model="filters[field.key]" class="ch-input" type="number">
                            </template>

                            <template x-if="field.type === 'date'">
                                <input x-model="filters[field.key]" class="ch-input" type="date">
                            </template>
                        </label>
                    </template>
                </div>

                <div class="mt-5 flex flex-wrap gap-3">
                    <button @click="loadRecords()" class="ch-btn-primary">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">filter_list</span>
                        Apply filters
                    </button>
                    <button @click="resetFilters()" class="ch-btn-secondary">
                        Clear filters
                    </button>
                </div>
                </section>

                <section id="data" class="ch-card mt-6 overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant bg-surface-bright p-4">
                        <div>
                            <h2 class="font-headline text-xl font-semibold leading-tight text-primary">Latest records</h2>
                            <p class="mt-1 text-sm text-on-surface-variant">Use the table below to review the latest available records for this dataset.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="text-sm text-on-surface-variant" x-text="records.length ? `${records.length} visible` : 'No rows'"></span>
                            <a class="ch-btn-primary" href="/downloads"><span class="material-symbols-outlined text-[18px]">download</span>Download CSV</a>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] ch-data-table">
                        <thead>
                            <tr>
                                <template x-for="column in activeColumns()" :key="column.key">
                                    <th x-text="column.label"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-if="loadingRecords">
                                <tr>
                                    <td :colspan="activeColumns().length" class="px-4 py-10 text-center text-on-surface-variant">Loading records...</td>
                                </tr>
                            </template>
                            <template x-if="!loadingRecords && records.length === 0">
                                <tr>
                                    <td :colspan="activeColumns().length" class="px-4 py-10 text-center text-on-surface-variant">No records match the current filters.</td>
                                </tr>
                            </template>
                            <template x-for="(record, recordIndex) in records" :key="recordIndex">
                                <tr>
                                    <template x-for="column in activeColumns()" :key="column.key">
                                        <td x-text="displayValue(record[column.key])"></td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="mt-5 flex items-center justify-between gap-4">
                    <div class="text-sm text-on-surface-variant" x-text="paginationLabel()"></div>
                    <div class="flex items-center gap-3">
                        <button
                            @click="goToPage(pagination.current_page - 1)"
                            :disabled="pagination.current_page <= 1 || loadingRecords"
                            class="ch-btn-secondary">
                            Previous
                        </button>
                        <button
                            @click="goToPage(pagination.current_page + 1)"
                            :disabled="pagination.current_page >= pagination.last_page || loadingRecords"
                            class="ch-btn-secondary">
                            Next
                        </button>
                    </div>
                </div>
                </section>
            </div>
        </div>
    </div>
@endsection

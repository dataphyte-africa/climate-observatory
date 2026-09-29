@extends('layouts.dataset-browser', ['title' => $title, 'activeNav' => 'profiles'])

@section('content')
    <div class="ch-shell grid gap-6 py-6 lg:grid-cols-[1fr_360px]">
        <main class="min-w-0">
            <div class="mb-6">
                <div class="flex items-center gap-2 text-sm text-on-surface-variant">
                    <a class="hover:text-secondary" href="/explore">Profiles</a>
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">chevron_right</span>
                    <span>{{ $code }}</span>
                </div>
                <h1 class="mt-2 font-headline text-2xl font-semibold leading-8 text-primary md:text-4xl md:leading-10">{{ $title }}</h1>
                <p class="mt-3 max-w-3xl text-base leading-7 text-on-surface-variant">{{ $summary }}</p>
            </div>

            <section class="ch-card h-[480px] overflow-hidden">
                <div class="ch-card-header">
                    <h2 class="font-headline text-base font-semibold">{{ $kind === 'country' ? 'Country coverage map' : 'Boundary highlight' }}</h2>
                    <div class="flex gap-2">
                        <button class="rounded p-1 hover:bg-white/20" title="Layers" type="button"><span class="material-symbols-outlined text-[18px]">layers</span></button>
                        <button class="rounded p-1 hover:bg-white/20" title="Download" type="button"><span class="material-symbols-outlined text-[18px]">download</span></button>
                    </div>
                </div>
                <div class="ch-abstract-map relative h-[calc(100%-48px)] overflow-hidden bg-surface-container-low">
                    <div class="ch-map-shape absolute left-1/2 top-1/2 h-[78%] w-[48%] -translate-x-1/2 -translate-y-1/2 bg-primary/25 ring-1 ring-primary/40"></div>
                    <div class="absolute left-4 top-4 rounded border border-outline-variant bg-white/92 p-3 text-sm shadow-sm">
                        <h2 class="font-semibold text-primary">Map and data state</h2>
                        <p class="mt-1 max-w-sm text-xs leading-5 text-on-surface-variant">{{ $mapStatus }}</p>
                    </div>
                    <div class="absolute bottom-4 left-4 rounded border border-outline-variant bg-white p-3 text-xs shadow-sm">
                        <span class="font-mono font-semibold text-primary">{{ $code }}</span>
                        <span class="ml-2 text-on-surface-variant">Selected code</span>
                    </div>
                </div>
            </section>

            <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['label' => 'Emissions', 'value' => 'Source-linked', 'icon' => 'factory'],
                    ['label' => 'Rainfall', 'value' => 'Partial states', 'icon' => 'cloudy_snowing'],
                    ['label' => 'Flood risk', 'value' => 'LGA-ready', 'icon' => 'warning'],
                    ['label' => 'Policy', 'value' => 'Document-backed', 'icon' => 'policy'],
                ] as $metric)
                    <article class="ch-card p-4">
                        <div class="flex items-start justify-between gap-3">
                            <span class="text-xs uppercase tracking-[0.08em] text-on-surface-variant">{{ $metric['label'] }}</span>
                            <span class="material-symbols-outlined text-secondary" aria-hidden="true">{{ $metric['icon'] }}</span>
                        </div>
                        <p class="mt-3 font-headline text-xl font-semibold text-primary">{{ $metric['value'] }}</p>
                    </article>
                @endforeach
            </section>

            <section class="mt-6 grid gap-6 lg:grid-cols-2">
                <article class="ch-card overflow-hidden">
                    <div class="ch-card-header-secondary">
                        <h2 class="font-headline text-base font-semibold">Emissions trend</h2>
                        <span class="text-xs">Static chart fixture</span>
                    </div>
                    <div class="relative h-64 p-5">
                        <div class="absolute inset-x-8 bottom-12 top-8 border-b border-l border-outline-variant">
                            <svg class="absolute inset-0 h-full w-full" preserveAspectRatio="none" viewBox="0 0 100 100">
                                <polyline points="0,82 14,77 28,70 42,60 56,47 70,38 84,31 100,22" fill="none" stroke="#00452e" stroke-width="3" vector-effect="non-scaling-stroke" />
                                <polyline points="0,90 14,84 28,78 42,72 56,65 70,58 84,52 100,44" fill="none" stroke="#67bafd" stroke-width="3" vector-effect="non-scaling-stroke" />
                            </svg>
                        </div>
                    </div>
                </article>
                <article class="ch-card overflow-hidden">
                    <div class="ch-card-header">
                        <h2 class="font-headline text-base font-semibold">Risk and hazard ranking</h2>
                        <span class="text-xs">Unavailable values labelled</span>
                    </div>
                    <div class="grid gap-3 p-4">
                        @foreach ([['Flood exposure', 84, '#ba1a1a'], ['Rainfall anomaly', 79, '#006399'], ['Readiness gap', 62, '#00452e']] as $row)
                            <div class="grid grid-cols-[1fr_auto] items-center gap-4">
                                <span class="text-sm text-on-surface">{{ $row[0] }}</span>
                                <span class="font-mono text-sm text-on-surface-variant">{{ $row[1] }}%</span>
                                <div class="col-span-2 h-2 rounded bg-surface-variant">
                                    <div class="h-2 rounded" style="width: {{ $row[1] }}%; background-color: {{ $row[2] }}"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>
            </section>

            <section class="mt-6">
                <div class="mb-4">
                    <h2 class="font-headline text-xl font-semibold text-primary">Available evidence</h2>
                    <p class="mt-2 text-sm text-on-surface-variant">Datasets to connect once filters are finalized.</p>
                </div>
                @include('public.partials.dataset-list', ['datasets' => $datasets])
            </section>
        </main>

        <aside class="flex flex-col gap-6">
            <section class="ch-card p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-secondary">{{ ucfirst($kind) }} profile</p>
                <h2 class="mt-2 font-headline text-2xl font-semibold text-primary">{{ $code }}</h2>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded border border-outline-variant bg-surface p-3">
                        <dt class="text-on-surface-variant">Map binding</dt>
                        <dd class="mt-1 font-semibold text-primary">Provisional</dd>
                    </div>
                    <div class="rounded border border-outline-variant bg-surface p-3">
                        <dt class="text-on-surface-variant">Raw files</dt>
                        <dd class="mt-1 font-semibold text-primary">Not exposed</dd>
                    </div>
                </dl>
            </section>

            <section class="ch-card p-4">
                <h2 class="font-headline text-xl font-semibold text-primary">Profile modules</h2>
                <div class="mt-4 grid gap-3">
                    @foreach ($sections as $section)
                        <article class="rounded border border-outline-variant bg-surface p-3">
                            <h3 class="font-semibold text-primary">{{ $section['title'] }}</h3>
                            <p class="mt-1 text-sm leading-6 text-on-surface-variant">{{ $section['state'] }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
        </aside>
    </div>
@endsection

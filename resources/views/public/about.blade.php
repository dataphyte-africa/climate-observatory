@extends('layouts.dataset-browser', ['title' => 'About ClimateHub', 'activeNav' => 'about'])

@section('content')
    <div class="ch-shell py-6">
        <section class="grid gap-8 lg:grid-cols-[0.95fr_1.05fr] lg:items-start">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.08em] text-secondary">About ClimateHub</p>
                <h1 class="mt-2 font-headline text-2xl font-semibold leading-8 text-primary md:text-4xl md:leading-10">ClimateHub helps public data users find Nigeria climate evidence with source and method context intact.</h1>
                <p class="mt-4 max-w-3xl text-base leading-7 text-on-surface-variant">The portal serves journalists, researchers, policy teams, civic organisations, students, and developers who need reusable climate data without losing provenance, coverage, caveats, or licence context.</p>
            </div>

            <section class="ch-card p-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded border border-outline-variant bg-surface p-4">
                        <p class="text-xs uppercase tracking-[0.08em] text-on-surface-variant">Active datasets</p>
                        <p class="mt-3 font-headline text-3xl font-semibold text-primary">{{ $datasetCount }}</p>
                    </div>
                    <div class="rounded border border-outline-variant bg-surface p-4">
                        <p class="text-xs uppercase tracking-[0.08em] text-on-surface-variant">Source records</p>
                        <p class="mt-3 font-headline text-3xl font-semibold text-secondary">{{ $sourceCount }}</p>
                    </div>
                </div>
            </section>
        </section>

        <section class="mt-8 grid gap-6 lg:grid-cols-3">
            <article class="ch-card p-5">
                <h2 class="font-headline text-xl font-semibold text-primary">Data standards</h2>
                <p class="mt-3 text-sm leading-6 text-on-surface-variant">Public views identify the source, method, coverage, unit, period, version, latest update, and missingness state for each data-bearing module.</p>
            </article>
            <article class="ch-card p-5">
                <h2 class="font-headline text-xl font-semibold text-primary">Database-backed content</h2>
                <p class="mt-3 text-sm leading-6 text-on-surface-variant">Climate facts and production content remain database-backed through Laravel/MySQL and Statamic Eloquent.</p>
            </article>
            <article class="ch-card p-5">
                <h2 class="font-headline text-xl font-semibold text-primary">Public safety states</h2>
                <p class="mt-3 text-sm leading-6 text-on-surface-variant">No-data, no-value, partial coverage, source-unavailable, loading, and map-error states are part of the interface contract.</p>
            </article>
        </section>

        <section class="ch-card mt-8 overflow-hidden">
            <div class="ch-card-header">
                <h2 class="font-headline text-base font-semibold">How evidence moves through the portal</h2>
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">timeline</span>
            </div>
            <div class="grid gap-4 p-5 md:grid-cols-4">
                @foreach ([
                    ['title' => 'Source', 'body' => 'Source institution, licence, citation, and update context are recorded.'],
                    ['title' => 'Version', 'body' => 'Published dataset versions keep row counts and activation timestamps visible.'],
                    ['title' => 'Interface', 'body' => 'Charts, maps, and tables show exact values and missingness labels.'],
                    ['title' => 'Reuse', 'body' => 'Downloads and APIs keep field context and citation requirements nearby.'],
                ] as $step)
                    <div class="rounded border border-outline-variant bg-surface p-4">
                        <h3 class="font-semibold text-primary">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-sm leading-6 text-on-surface-variant">{{ $step['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
@endsection

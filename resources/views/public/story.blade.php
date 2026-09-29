@extends('layouts.dataset-browser', ['title' => $title, 'activeNav' => 'stories'])

@section('content')
    <div class="ch-shell py-6">
        <section class="grid gap-6 lg:grid-cols-[1fr_380px]">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.08em] text-secondary">Story</p>
                <h1 class="mt-2 font-headline text-2xl font-semibold leading-8 text-primary md:text-4xl md:leading-10">{{ $title }}</h1>
                <p class="mt-3 max-w-3xl text-base leading-7 text-on-surface-variant">Editorial narrative and source-backed evidence modules will be finalized from EDIT-01 content. Until then, this public route exposes the linked dataset evidence without inventing narrative claims.</p>
            </div>
            <aside class="ch-card p-4">
                <h2 class="font-headline text-xl font-semibold text-primary">Evidence modules</h2>
                <div class="mt-4 grid gap-3">
                    @foreach ($storyModules as $module)
                        <article class="rounded border border-outline-variant bg-surface p-3">
                            <h3 class="font-semibold text-primary">{{ $module['title'] }}</h3>
                            <p class="mt-1 text-sm leading-6 text-on-surface-variant">{{ $module['state'] }}</p>
                        </article>
                    @endforeach
                </div>
            </aside>
        </section>

        <section class="ch-card mt-6 overflow-hidden">
            <div class="ch-card-header">
                <h2 class="font-headline text-base font-semibold">Rainfall and flood evidence path</h2>
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">timeline</span>
            </div>
            <div class="grid gap-4 p-5 md:grid-cols-3">
                <div class="rounded border border-outline-variant bg-surface p-4">
                    <h3 class="font-semibold text-primary">Rainfall context</h3>
                    <p class="mt-2 text-sm leading-6 text-on-surface-variant">Use rainfall records and no-value states while map binding remains provisional.</p>
                </div>
                <div class="rounded border border-outline-variant bg-surface p-4">
                    <h3 class="font-semibold text-primary">Flood impacts</h3>
                    <p class="mt-2 text-sm leading-6 text-on-surface-variant">Show people, place, date, source, and unavailable values as reported by the source.</p>
                </div>
                <div class="rounded border border-outline-variant bg-surface p-4">
                    <h3 class="font-semibold text-primary">Method and caveats</h3>
                    <p class="mt-2 text-sm leading-6 text-on-surface-variant">Keep methodology, period, version, unit, and citation visible near the evidence.</p>
                </div>
            </div>
        </section>

        <section class="mt-6">
            <div class="mb-4">
                <h2 class="font-headline text-xl font-semibold text-primary">Source-backed datasets for this story route.</h2>
            </div>
            @include('public.partials.dataset-list', ['datasets' => $datasets])
        </section>
    </div>
@endsection

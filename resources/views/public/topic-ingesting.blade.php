@extends('layouts.dataset-browser', ['title' => $topic, 'activeNav' => 'themes'])

@section('content')
    <main class="ch-shell flex min-h-[calc(100vh-16rem)] items-center justify-center py-12">
        <section class="max-w-md text-center">
            <span class="material-symbols-outlined text-[36px] text-secondary" aria-hidden="true">database</span>
            <p class="mt-4 text-xs font-semibold uppercase tracking-[0.08em] text-secondary">{{ $topic }}</p>
            <h1 class="mt-2 font-headline text-2xl font-semibold text-primary">Data ingestion under development</h1>
            <p class="mt-3 text-sm leading-6 text-on-surface-variant">This topic will be published when its data is ready for review and release.</p>
        </section>
    </main>
@endsection

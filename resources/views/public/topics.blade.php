@extends('layouts.dataset-browser', ['title' => 'Climate topics', 'activeNav' => 'topics'])

@section('content')
    <main class="ch-shell py-8 md:py-12">
        <section class="max-w-3xl">
            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-secondary">Climate topics</p>
            <h1 class="mt-3 font-headline text-3xl font-semibold leading-tight text-primary md:text-4xl">Explore climate topics</h1>
            <p class="mt-4 text-base leading-7 text-on-surface-variant">Choose a topic to open its visualisation and source-backed data.</p>
        </section>

        <section class="mt-8 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3" aria-label="Climate topics">
            @foreach ($topics as $topic)
                <a class="ch-source-card flex min-h-[190px] flex-col justify-between p-5 transition-colors hover:border-secondary hover:bg-surface-container-high" href="{{ $topic['href'] }}">
                    <div>
                        <span class="material-symbols-outlined text-[28px] text-secondary" aria-hidden="true">{{ $topic['icon'] }}</span>
                        <h2 class="mt-5 font-headline text-xl font-semibold text-primary">{{ $topic['title'] }}</h2>
                        <p class="mt-2 text-sm leading-6 text-on-surface-variant">{{ $topic['summary'] }}</p>
                    </div>
                    <span class="mt-5 inline-flex items-center gap-1 text-sm font-semibold text-secondary">
                        Open topic
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
                    </span>
                </a>
            @endforeach
        </section>
    </main>
@endsection

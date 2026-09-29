@extends('layouts.dataset-browser', ['title' => $title, 'activeNav' => 'methodologies'])

@section('content')
    <div class="ch-shell grid gap-6 py-6 lg:grid-cols-[1fr_340px]">
        <main class="min-w-0">
            <div class="mb-6">
                <div class="flex items-center gap-2 text-sm text-on-surface-variant">
                    <a class="hover:text-secondary" href="/downloads">Methodologies</a>
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">chevron_right</span>
                    <span>{{ $code }}</span>
                </div>
                <h1 class="mt-2 font-headline text-2xl font-semibold leading-8 text-primary md:text-4xl md:leading-10">{{ $title }}</h1>
                <p class="mt-3 max-w-3xl text-base leading-7 text-on-surface-variant">This page groups datasets that reference the same methodology code. Detailed editorial method copy waits for database-backed Statamic methodology records.</p>
            </div>

            <section class="ch-card overflow-hidden">
                <div class="ch-card-header">
                    <h2 class="font-headline text-base font-semibold">How subnational rainfall is measured</h2>
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">menu_book</span>
                </div>
                <div class="grid gap-4 p-5 lg:grid-cols-3">
                @foreach ($fallbackSections as $section)
                    <article class="rounded border border-outline-variant bg-surface p-4">
                        <h2 class="font-headline text-xl font-semibold text-primary">{{ $section['title'] }}</h2>
                        <p class="mt-3 text-sm leading-6 text-on-surface-variant">{{ $section['body'] }}</p>
                    </article>
                @endforeach
                </div>
            </section>

            <section class="mt-6">
                <h2 class="mb-4 font-headline text-xl font-semibold text-primary">Related datasets</h2>
            @include('public.partials.dataset-list', ['datasets' => $datasets])
            </section>
        </main>

        <aside class="ch-card h-fit p-4 lg:sticky lg:top-24">
            <h2 class="font-headline text-xl font-semibold text-primary">Method state</h2>
            <dl class="mt-4 grid gap-3 text-sm">
                <div class="rounded border border-outline-variant bg-surface p-3">
                    <dt class="text-on-surface-variant">Methodology code</dt>
                    <dd class="mt-1 break-all font-mono font-semibold text-primary">{{ $code }}</dd>
                </div>
                <div class="rounded border border-outline-variant bg-surface p-3">
                    <dt class="text-on-surface-variant">Editorial binding</dt>
                    <dd class="mt-1 font-semibold text-primary">Static placeholder pending DB content</dd>
                </div>
                <div class="rounded border border-outline-variant bg-surface p-3">
                    <dt class="text-on-surface-variant">Missing values</dt>
                    <dd class="mt-1 font-semibold text-primary">Shown as not reported</dd>
                </div>
            </dl>
        </aside>
    </div>
@endsection

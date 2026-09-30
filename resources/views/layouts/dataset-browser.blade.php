<!doctype html>
<html lang="en" class="light">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'ClimateHub Data Portal' }}</title>
        @vite(['resources/js/site.js', 'resources/css/site.css'])
    </head>
    <body class="min-h-screen overflow-x-hidden bg-background font-body text-on-surface antialiased selection:bg-primary-container selection:text-on-primary-container">
        <div class="flex min-h-screen flex-col">
            <nav class="border-b border-outline-variant bg-surface">
                <div class="ch-shell flex h-16 items-center">
                    <div class="flex min-w-0 items-center gap-2 sm:gap-6">
                        <a class="flex items-center gap-2 font-headline text-xl font-bold text-primary" href="{{ route('home') }}" aria-label="ClimateHub home">
                            <img class="h-8 w-8 object-contain" src="/images/climatehub-mark.png" alt="">
                            <span>ClimateHub</span>
                        </a>
                        <div class="flex items-center gap-2 sm:gap-4">
                            <a class="{{ ($activeNav ?? 'home') === 'downloads' ? 'border-b-2 border-secondary pb-1 text-secondary' : 'text-on-surface-variant' }} text-sm leading-6 transition-colors hover:text-secondary sm:text-base" href="{{ route('downloads') }}">Explore data</a>
                            <details class="group relative">
                                <summary class="{{ in_array(($activeNav ?? 'home'), ['topics', 'themes'], true) ? 'border-b-2 border-secondary pb-1 text-secondary' : 'text-on-surface-variant' }} flex cursor-pointer list-none items-center gap-0.5 text-sm leading-6 transition-colors hover:text-secondary [&::-webkit-details-marker]:hidden sm:text-base">
                                    Topics
                                    <span class="material-symbols-outlined text-[16px] transition-transform group-open:rotate-180" aria-hidden="true">expand_more</span>
                                </summary>
                                <div class="absolute left-0 top-full z-30 mt-2 grid w-52 overflow-hidden rounded border border-outline-variant bg-surface-container-lowest py-1 shadow-ambient">
                                    @foreach ([
                                        ['label' => 'Emissions', 'href' => '/emissions'],
                                        ['label' => 'Rainfall', 'href' => '/rainfall'],
                                        ['label' => 'Floods', 'href' => '/floods'],
                                        ['label' => 'Risk', 'href' => '/risk'],
                                        ['label' => 'Policy', 'href' => '/policy'],
                                        ['label' => 'Finance', 'href' => '/finance'],
                                        ['label' => 'Environment', 'href' => '/environment'],
                                    ] as $topic)
                                        <a class="px-3 py-2 text-sm text-on-surface transition-colors hover:bg-surface-container hover:text-secondary" href="{{ $topic['href'] }}">{{ $topic['label'] }}</a>
                                    @endforeach
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
            </nav>

            <main class="flex-1">
                @yield('content')
            </main>

            <footer class="mt-12 border-t border-outline-variant bg-surface-container-highest px-6 py-8">
                <div class="mx-auto grid max-w-[1280px] grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <a class="flex w-fit items-center gap-2 font-headline text-xl font-semibold text-primary" href="{{ route('home') }}">
                            <img class="h-7 w-7 object-contain" src="/images/climatehub-mark.png" alt="">
                            <span>ClimateHub</span>
                        </a>
                        <p class="mt-2 max-w-xs text-sm leading-5 text-on-surface-variant">Nigeria's central repository for climate intelligence and environmental data.</p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <span class="text-xs font-semibold uppercase tracking-[0.05em] text-on-surface">Resources</span>
                        <a class="text-sm leading-5 text-on-surface-variant opacity-80 transition-opacity hover:text-secondary hover:opacity-100" href="/downloads">Download data</a>
                    </div>
                    <div class="mt-4 flex flex-col justify-end md:mt-0 md:items-end">
                        <span class="text-sm leading-5 text-on-surface-variant">© {{ now()->year }} ClimateHub Data Portal. All rights reserved.</span>
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>

@extends('layouts.dataset-browser', ['title' => 'ClimateHub Data Portal', 'activeNav' => 'home'])

@section('content')
    <main>
        <section class="ch-home-hero" x-data="homeHeroCarousel({{ $featuredImages->count() }})">
            @foreach ($featuredImages as $index => $slide)
                <div
                    x-cloak
                    x-show="activeSlide === {{ $index }}"
                    x-transition.opacity.duration.300ms
                    class="ch-home-hero-image-layer"
                    style="background-image: linear-gradient(90deg, rgb(0 22 11 / 91%) 0%, rgb(0 22 11 / 74%) 41%, rgb(0 22 11 / 46%) 100%), url('{{ $slide['url'] }}');"
                    role="img"
                    aria-label="{{ $slide['alt'] }}"
                ></div>
            @endforeach
            <div class="ch-shell flex h-[920px] flex-col justify-center gap-8 py-10 md:h-[940px] md:gap-10 md:py-14">
            <div class="max-w-3xl space-y-4">
                <div class="inline-flex items-center gap-1 rounded border border-white/30 bg-primary/70 px-2 py-1 text-xs font-semibold text-white">
                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">public</span>
                    Nigeria climate intelligence
                </div>
                <h1 class="max-w-[560px] font-headline text-2xl font-semibold leading-8 text-white md:text-4xl md:leading-[2.8rem]">
                    Explore the evidence behind Nigeria's changing climate.
                </h1>
                <p class="max-w-[500px] text-base leading-6 text-white/90 md:text-lg md:leading-7">
                    Find emissions, rainfall, flood, risk, policy, finance, and environmental data from one public data portal.
                </p>
                <div class="flex flex-wrap gap-3 pt-2">
                    <a class="inline-flex items-center justify-center rounded bg-white px-5 py-2.5 text-xs font-semibold text-primary transition-colors hover:bg-primary-fixed" href="{{ route('downloads') }}">Explore the data</a>
                    <a class="inline-flex items-center justify-center rounded border border-white/70 px-5 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-white/15" href="{{ route('topics.index') }}">Browse climate topics</a>
                </div>
            </div>

            <section class="relative h-[430px] w-full rounded-lg border border-white/50 bg-surface-container-lowest/95 shadow-ambient backdrop-blur-sm" x-data="summaryCarousel()" aria-label="Climate data summaries">
                <div class="absolute right-4 top-4 z-10 flex items-center gap-2">
                    <button class="flex h-8 w-8 items-center justify-center rounded border border-outline-variant bg-surface-container-lowest text-secondary disabled:cursor-not-allowed disabled:opacity-40" type="button" aria-label="Show previous summary" @click="previous()"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span></button>
                    <span class="text-xs font-semibold text-on-surface-variant" x-text="`${activeSlide + 1} of ${totalSlides}`"></span>
                    <button class="flex h-8 w-8 items-center justify-center rounded border border-outline-variant bg-surface-container-lowest text-secondary disabled:cursor-not-allowed disabled:opacity-40" type="button" aria-label="Show next summary" @click="next()"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span></button>
                </div>
                <a x-cloak :class="{ 'pointer-events-auto opacity-100': activeSlide === 0, 'pointer-events-none opacity-0': activeSlide !== 0 }" :aria-hidden="activeSlide === 0 ? 'false' : 'true'" :tabindex="activeSlide === 0 ? 0 : -1" class="group absolute inset-0 overflow-hidden transition-opacity duration-300 ease-out hover:bg-surface-container-low" href="/rainfall" aria-label="Open the rainfall explorer showing monthly rainfall against the seasonal norm">
                    <div class="h-full w-full" x-data="rainfallNormalPreview()" x-effect="activeSlide === 0 && load()">
                        <div class="flex h-full w-full flex-col p-5 pr-24">
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-on-surface-variant">Monthly rainfall condition</p>
                                <h2 class="mt-0.5 font-headline text-lg font-semibold text-primary">Latest monthly rainfall across Nigeria</h2>
                            </div>
                            <div class="grid min-h-0 flex-1 grid-cols-[minmax(145px,0.32fr)_minmax(0,1fr)] gap-3 pt-3 sm:grid-cols-[minmax(190px,0.3fr)_minmax(0,1fr)]">
                                <div class="flex min-h-0 flex-col justify-between pb-1">
                                    <dl class="space-y-3">
                                        <div class="border-b border-outline-variant pb-2">
                                            <dt class="text-[10px] font-medium text-on-surface-variant">Latest national average</dt>
                                            <dd class="mt-0.5 font-headline text-xl font-semibold leading-none text-primary" x-text="metric(latestAverage)">Loading</dd>
                                        </div>
                                        <div class="border-b border-outline-variant pb-2">
                                            <dt class="text-[10px] font-medium text-on-surface-variant">Seasonal norm</dt>
                                            <dd class="mt-0.5 font-headline text-xl font-semibold leading-none text-primary" x-text="metric(seasonalNormal)">Loading</dd>
                                        </div>
                                        <div>
                                            <dt class="text-[10px] font-medium text-on-surface-variant">Difference from seasonal norm</dt>
                                            <dd class="mt-0.5 font-headline text-xl font-semibold leading-none text-secondary" x-text="differenceLabel()">Loading</dd>
                                            <dd class="mt-1 text-[10px] text-on-surface-variant" x-text="differenceDescription()">Against seasonal norm</dd>
                                        </div>
                                    </dl>
                                    <div class="mt-3 space-y-1 text-[10px] text-on-surface-variant">
                                        <div class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-full bg-[#00796b]"></i>Above normal</div>
                                        <div class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-full bg-[#9bcfca]"></i>Near normal</div>
                                        <div class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-full bg-[#e7b45f]"></i>Below normal</div>
                                        <div class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-full bg-[#d9dddb]"></i>Data unavailable</div>
                                    </div>
                                </div>
                                <div class="relative min-h-[250px]">
                                <svg class="h-full w-full" viewBox="0 0 600 340" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Nigeria states coloured by latest monthly rainfall against the seasonal norm">
                                    <g x-html="mapMarkup"></g>
                                    <text x="300" y="175" text-anchor="middle" fill="#4d5652" font-size="15" x-show="loading">Loading rainfall map</text>
                                    <text x="300" y="175" text-anchor="middle" fill="#4d5652" font-size="15" x-show="error">Rainfall map unavailable</text>
                                </svg>
                            </div>
                        </div>
                        <span class="absolute bottom-5 right-5 text-sm font-semibold text-secondary">Open rainfall explorer <span class="material-symbols-outlined align-middle text-[16px]" aria-hidden="true">arrow_forward</span></span>
                    </div>
                </a>
                <a x-cloak :class="{ 'pointer-events-auto opacity-100': activeSlide === 1, 'pointer-events-none opacity-0': activeSlide !== 1 }" :aria-hidden="activeSlide === 1 ? 'false' : 'true'" :tabindex="activeSlide === 1 ? 0 : -1" class="group absolute inset-0 flex overflow-hidden transition-opacity duration-300 ease-out hover:bg-surface-container-low" href="/emissions" aria-label="Open the emissions explorer">
                    @if ($emissionsSummary->isNotEmpty())
                    @endif
                    <div class="relative z-[1] flex h-full w-full flex-col p-5">
                        <div class="pr-24">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-on-surface-variant">Emissions coverage map</p>
                            <h2 class="mt-0.5 font-headline text-lg font-semibold text-primary">Nigeria in global energy-sector emissions</h2>
                        </div>
                        @if ($emissionsSummary->isNotEmpty() && $emissionsContext)
                            <div class="grid min-h-0 flex-1 grid-cols-[minmax(145px,0.32fr)_minmax(0,1fr)] gap-3 pt-3 sm:grid-cols-[minmax(190px,0.3fr)_minmax(0,1fr)]" x-data="globalEmissionsPreview(@js($emissionsSummary))">
                                <div class="flex min-h-0 flex-col justify-between pb-1">
                                    <dl class="space-y-3">
                                        <div class="border-b border-outline-variant pb-2">
                                            <dt class="text-[10px] font-medium text-on-surface-variant">Latest reported total</dt>
                                            <dd class="mt-0.5 text-xl font-semibold leading-none text-primary">{{ number_format($emissionsContext['value'], 1) }}</dd>
                                            <dd class="mt-1 text-[10px] text-on-surface-variant">Source-provided value</dd>
                                        </div>
                                        <div class="border-b border-outline-variant pb-2">
                                            <dt class="text-[10px] font-medium text-on-surface-variant">Latest reporting year</dt>
                                            <dd class="mt-0.5 text-xl font-semibold leading-none text-primary">{{ $emissionsSummaryYear }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-[10px] font-medium text-on-surface-variant">Rank among published countries</dt>
                                            <dd class="mt-0.5 text-xl font-semibold leading-none text-primary">#{{ $emissionsContext['rank'] }}</dd>
                                            <dd class="mt-1 text-[10px] text-on-surface-variant">of {{ $emissionsContext['country_count'] }} in this view</dd>
                                        </div>
                                    </dl>
                                    <div class="mt-3 space-y-1 text-[10px] text-on-surface-variant">
                                        <div class="h-2 w-full max-w-[170px] bg-gradient-to-r from-[#c2e5db] via-[#5ea99f] to-[#063f39]"></div>
                                        <div class="flex max-w-[170px] justify-between"><span>Lower value</span><span>Higher value</span></div>
                                        <div class="flex items-center gap-1.5 pt-1"><span class="h-2.5 w-2.5 bg-[#dce3df]"></span><span>Grey: no source value</span></div>
                                    </div>
                                </div>
                                <div class="min-h-0">
                                    <svg class="h-full w-full" viewBox="0 0 720 360" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Global map with published energy-sector total greenhouse-gas values for {{ $emissionsSummaryYear }}">
                                        <g x-html="mapMarkup"></g>
                                    </svg>
                                </div>
                            </div>
                        @else
                            <div class="flex flex-1 items-center justify-center text-center text-sm text-on-surface-variant">No published emissions summary is available.</div>
                        @endif
                        <span class="mt-2 text-right text-sm font-semibold text-secondary">Open emissions explorer <span class="material-symbols-outlined align-middle text-[16px]" aria-hidden="true">arrow_forward</span></span>
                    </div>
                </a>
            </section>
            </div>
        </section>

        <div class="ch-shell py-10 md:py-12">
        <section class="mb-12">
            <h2 class="ch-section-title">Climate Topics</h2>
            <div class="grid grid-cols-2 gap-2 md:grid-cols-4 lg:grid-cols-7">
                @foreach ($topics as $topic)
                    <a class="ch-source-card items-center justify-center gap-2 text-center" href="{{ $topic['href'] }}">
                        <span class="material-symbols-outlined text-[32px] text-on-surface-variant transition-colors group-hover:text-secondary" aria-hidden="true">{{ $topic['icon'] }}</span>
                        <span class="text-xs font-semibold text-on-surface">{{ $topic['title'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="mb-12" aria-label="Latest data">
            <div class="mb-4 flex items-end justify-between border-b border-outline-variant pb-2">
                <h2 class="font-headline text-xl font-semibold leading-7 text-primary">Latest data</h2>
                <a class="flex items-center gap-1 text-xs font-semibold uppercase tracking-[0.05em] text-secondary hover:underline" href="/downloads">
                    Explore data
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_forward</span>
                </a>
            </div>
            @include('public.partials.home-dataset-list', ['datasets' => $downloadDatasets, 'datasetTopics' => $datasetTopics])
        </section>

        <section class="border-t border-outline-variant pt-4">
            <div class="flex items-start gap-2 text-on-surface-variant">
                <span class="material-symbols-outlined mt-1 text-[16px]" aria-hidden="true">info</span>
                <div>
                    <p class="text-sm leading-5">
                        <strong class="text-xs font-semibold text-on-surface">Source &amp; Update Notes:</strong>
                        Data is aggregated from official government agencies, international climate bodies, and verified research institutions.
                    </p>
                </div>
            </div>
        </section>
        </div>
    </main>
@endsection

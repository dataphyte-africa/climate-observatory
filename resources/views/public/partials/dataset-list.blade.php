<div class="relative isolate overflow-x-auto rounded-lg border border-outline-variant bg-surface-container-lowest">
    <div class="min-w-[760px]">
        <div class="grid grid-cols-[minmax(300px,1fr)_minmax(112px,0.28fr)_minmax(96px,0.2fr)_64px] gap-3 border-b border-outline-variant bg-surface-container-low px-4 py-3 text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant sm:gap-5 sm:px-5">
            <span>Dataset</span>
            <span>Source</span>
            <span>Coverage</span>
            <span class="sticky right-0 z-20 border-l border-outline-variant bg-surface-container-low text-center">File</span>
        </div>

        @forelse ($datasets as $dataset)
            @php($latestVersion = $dataset->versions->first())
            <article class="grid grid-cols-[minmax(300px,1fr)_minmax(112px,0.28fr)_minmax(96px,0.2fr)_64px] items-center gap-3 border-b border-outline-variant px-4 py-4 last:border-b-0 sm:gap-5 sm:px-5">
                <div class="min-w-0">
                    <h3 class="font-headline text-base font-semibold leading-5 text-primary">{{ $dataset->title }}</h3>
                    <p class="mt-1 line-clamp-2 text-sm leading-5 text-on-surface-variant">{{ $dataset->summary }}</p>
                </div>
                <p class="text-sm font-medium text-on-surface">{{ $dataset->source?->name ?? 'Source unavailable' }}</p>
                <p class="text-sm font-medium text-on-surface">{{ $latestVersion?->version_label ?? 'Source-defined' }}</p>
                <div class="sticky right-0 z-10 flex justify-center border-l border-outline-variant bg-surface-container-lowest py-1">
                    @if ($dataset->download_enabled && $dataset->api_enabled)
                        <a class="inline-flex h-10 w-10 items-center justify-center rounded bg-primary text-on-primary transition-colors hover:bg-primary-container" href="/api/datasets/{{ $dataset->code }}/export.csv" aria-label="Download {{ $dataset->title }} as CSV" title="Download CSV">
                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">download</span>
                        </a>
                    @else
                        <span class="inline-flex h-10 w-10 items-center justify-center text-on-surface-variant" title="Download unavailable">
                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">block</span>
                        </span>
                    @endif
                </div>
            </article>
        @empty
            <div class="px-5 py-10 text-center text-on-surface-variant">
                No published datasets are available for this view yet.
            </div>
        @endforelse
    </div>
</div>

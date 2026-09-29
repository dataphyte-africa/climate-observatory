@php
    $sortable = $sortable ?? false;
    $paginate = $paginate ?? false;
    $sort = $sort ?? 'dataset';
    $direction = $direction ?? 'asc';
    $columns = [
        ['key' => 'dataset', 'label' => 'Dataset', 'class' => ''],
        ['key' => 'topics', 'label' => 'Topics', 'class' => ''],
        ['key' => 'source', 'label' => 'Source', 'class' => ''],
        ['key' => 'downloads', 'label' => 'Downloads', 'class' => 'text-right'],
        ['key' => 'file', 'label' => 'File', 'class' => 'text-center'],
    ];
@endphp

<div class="overflow-hidden border border-outline-variant bg-surface-container-lowest">
    <div class="hidden grid-cols-[minmax(0,1fr)_minmax(10rem,.4fr)_minmax(9rem,.28fr)_6.5rem_3.5rem] gap-5 border-b border-outline-variant bg-surface-container-low px-5 py-3 text-xs font-semibold uppercase tracking-[0.05em] text-on-surface-variant lg:grid">
        @foreach ($columns as $column)
            @php
                $isActive = $sort === $column['key'];
                $nextDirection = $isActive && $direction === 'asc' ? 'desc' : 'asc';
            @endphp
            @if ($sortable)
                <a class="inline-flex items-center gap-1 {{ $column['class'] }} hover:text-primary" href="{{ route('downloads', ['sort' => $column['key'], 'direction' => $nextDirection]) }}" aria-label="Sort by {{ strtolower($column['label']) }}">
                    <span>{{ $column['label'] }}</span>
                    <span class="material-symbols-outlined text-[15px]" aria-hidden="true">{{ $isActive ? ($direction === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'unfold_more' }}</span>
                </a>
            @else
                <span class="{{ $column['class'] }}">{{ $column['label'] }}</span>
            @endif
        @endforeach
    </div>

    @forelse ($datasets as $dataset)
            @php($topics = $datasetTopics->get($dataset->code, collect()))
            <article class="grid gap-4 border-b border-outline-variant px-5 py-4 last:border-b-0 lg:grid-cols-[minmax(0,1fr)_minmax(10rem,.4fr)_minmax(9rem,.28fr)_6.5rem_3.5rem] lg:items-center lg:gap-5">
                <div class="min-w-0">
                <h3 class="font-headline text-base font-semibold leading-5 text-primary">{{ $dataset->title }}</h3>
                <p class="mt-1 line-clamp-1 text-sm leading-5 text-on-surface-variant">{{ $dataset->summary }}</p>
            </div>

            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-on-surface-variant lg:hidden">Topics</p>
                <div class="mt-1 flex flex-wrap gap-1.5 lg:mt-0">
                    @forelse ($topics->take(3) as $topic)
                        <a class="rounded border border-primary-container bg-primary-fixed px-2 py-1 text-xs font-semibold text-primary transition-colors hover:bg-primary-container" href="{{ $topic['url'] }}">{{ $topic['title'] }}</a>
                    @empty
                        <span class="text-sm text-on-surface-variant">Unclassified</span>
                    @endforelse
                    @if ($topics->count() > 3)
                        <span class="rounded border border-outline-variant px-2 py-1 text-xs font-semibold text-on-surface-variant">+{{ $topics->count() - 3 }}</span>
                    @endif
                </div>
            </div>

            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-on-surface-variant lg:hidden">Source</p>
                <p class="mt-1 text-sm font-medium text-on-surface lg:mt-0">{{ $dataset->source?->name ?? 'Source unavailable' }}</p>
            </div>

            <div class="flex items-baseline justify-between lg:block lg:text-right">
                <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-on-surface-variant lg:hidden">Downloads</p>
                <p class="mt-1 text-sm font-semibold tabular-nums text-on-surface">{{ number_format($dataset->download_count) }}</p>
            </div>

            <div class="flex items-center justify-between border-t border-outline-variant pt-3 lg:justify-center lg:border-t-0 lg:pt-0">
                <span class="text-[10px] font-semibold uppercase tracking-[0.05em] text-on-surface-variant lg:hidden">File</span>
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

@if ($paginate && $datasets->hasPages())
    <nav class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="Dataset pagination">
        <p class="text-on-surface-variant">Showing {{ $datasets->firstItem() }}-{{ $datasets->lastItem() }} of {{ $datasets->total() }} datasets</p>
        <div class="flex items-center gap-2">
            @if ($datasets->previousPageUrl())
                <a class="inline-flex h-9 items-center gap-1 border border-outline-variant px-3 font-semibold text-primary hover:bg-surface-container-low" href="{{ $datasets->previousPageUrl() }}">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                    Previous
                </a>
            @else
                <span class="inline-flex h-9 items-center gap-1 border border-outline-variant px-3 font-semibold text-on-surface-variant" aria-disabled="true">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                    Previous
                </span>
            @endif
            <span class="font-medium tabular-nums text-on-surface">Page {{ $datasets->currentPage() }} of {{ $datasets->lastPage() }}</span>
            @if ($datasets->nextPageUrl())
                <a class="inline-flex h-9 items-center gap-1 border border-outline-variant px-3 font-semibold text-primary hover:bg-surface-container-low" href="{{ $datasets->nextPageUrl() }}">
                    Next
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
                </a>
            @else
                <span class="inline-flex h-9 items-center gap-1 border border-outline-variant px-3 font-semibold text-on-surface-variant" aria-disabled="true">
                    Next
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
                </span>
            @endif
        </div>
    </nav>
@endif

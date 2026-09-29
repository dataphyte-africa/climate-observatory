@if ($paginator->hasPages())
    @php
        $lastPage = $paginator->lastPage();
        $currentPage = $paginator->currentPage();
        $pages = array_values(array_unique(array_filter([
            1,
            2,
            3,
            $currentPage - 1,
            $currentPage,
            $currentPage + 1,
            $lastPage - 1,
            $lastPage,
        ], fn (int $page): bool => $page >= 1 && $page <= $lastPage)));
        sort($pages);
        $previousPage = null;
    @endphp

    <nav class="rainfall-pagination" aria-label="{{ $ariaLabel ?? 'Rainfall fact pagination' }}">
        <span class="rainfall-pagination__previous">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif
        </span>

        <span class="rainfall-pagination__pages">
            @foreach ($pages as $page)
                @if ($previousPage !== null && $page > $previousPage + 1)
                    <span class="is-gap" aria-hidden="true">...</span>
                @endif

                @if ($page === $currentPage)
                    <span class="is-current" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                @endif

                @php($previousPage = $page)
            @endforeach
        </span>

        <span class="rainfall-pagination__next">
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span aria-disabled="true">Next</span>
            @endif
        </span>
    </nav>
@endif

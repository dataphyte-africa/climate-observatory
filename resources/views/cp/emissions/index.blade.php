@extends('statamic::layout')

@section('title', 'Emissions data')

@push('head')
    <style>
        .emissions-workspace { max-width: 1120px; margin: 0 auto; padding: 2rem 0 3rem; color: var(--theme-color-gray-900); }
        .emissions-workspace h1, .emissions-workspace h2, .emissions-workspace p { margin: 0; }
        .emissions-heading { font-size: 1.7rem; font-weight: 700; line-height: 1.2; }
        .emissions-intro, .emissions-copy { margin-top: .65rem !important; color: var(--theme-color-gray-600); }
        .emissions-status { margin-top: 1.25rem !important; border: 1px solid var(--theme-color-green-300); border-radius: .5rem; background: var(--theme-color-green-50); color: var(--theme-color-green-800); padding: .8rem 1rem; font-size: .9rem; }
        .emissions-stats { display: grid; gap: 1rem; grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top: 1.75rem; }
        .emissions-stat, .emissions-panel { border: 1px solid var(--theme-color-gray-300); border-radius: .6rem; background: var(--theme-color-content-bg); }
        .emissions-stat { padding: 1rem 1.1rem; }
        .emissions-label { color: var(--theme-color-gray-500); font-size: .73rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .emissions-number { margin-top: .45rem !important; font-size: 1.45rem; font-weight: 700; }
        .emissions-panel { margin-top: 1.5rem; overflow: hidden; }
        .emissions-panel__header { padding: 1.1rem 1.25rem; border-bottom: 1px solid var(--theme-color-gray-300); }
        .emissions-panel__title { font-size: 1.05rem; font-weight: 700; }
        .emissions-panel__body { padding: 1.25rem; }
        .emissions-upload, .emissions-filters { display: grid; gap: .9rem; }
        .emissions-upload { grid-template-columns: minmax(0, 1fr) auto; align-items: end; }
        .emissions-filters { grid-template-columns: minmax(7rem, .8fr) minmax(10rem, 1.1fr) minmax(9rem, 1fr) minmax(6.5rem, .7fr) minmax(6.5rem, .7fr) 6rem auto; align-items: end; margin-top: 1rem; }
        .emissions-field { display: grid; gap: .35rem; color: var(--theme-color-gray-700); font-size: .84rem; font-weight: 600; }
        .emissions-field input, .emissions-field select { width: 100%; min-height: 2.55rem; border: 1px solid var(--theme-color-gray-300); border-radius: .4rem; background: var(--theme-color-content-bg); padding: .45rem .65rem; color: var(--theme-color-gray-900); font: inherit; font-weight: 400; }
        .emissions-button { min-height: 2.55rem; border: 1px solid #2563eb; border-radius: .4rem; background: #2563eb; color: #fff; cursor: pointer; padding: .45rem .9rem; font-size: .85rem; font-weight: 700; white-space: nowrap; }
        .emissions-button:hover { background: #1d4ed8; border-color: #1d4ed8; }
        .emissions-button--secondary { background: #fff; color: #1d4ed8; }
        .emissions-button--secondary:hover { background: #eff6ff; }
        .emissions-button:focus-visible { outline: 3px solid #93c5fd; outline-offset: 2px; }
        .emissions-table-wrap { overflow-x: auto; }
        .emissions-table { width: 100%; min-width: 760px; border-collapse: collapse; font-size: .9rem; }
        .emissions-table th { background: var(--theme-color-gray-50); color: var(--theme-color-gray-600); padding: .8rem 1rem; text-align: left; font-size: .72rem; letter-spacing: .04em; text-transform: uppercase; }
        .emissions-table td { border-top: 1px solid var(--theme-color-gray-200); padding: .85rem 1rem; vertical-align: middle; }
        .emissions-table .number { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; }
        .emissions-import-status { display: inline-flex; border-radius: 999px; background: var(--theme-color-gray-100); color: var(--theme-color-gray-700); padding: .2rem .5rem; font-size: .73rem; font-weight: 700; text-transform: capitalize; }
        .emissions-import-summary, .emissions-muted { color: var(--theme-color-gray-600); font-size: .83rem; }
        .emissions-import-duplicate { display: block; margin-top: .3rem; color: var(--theme-color-amber-800); font-size: .78rem; font-weight: 700; }
        .emissions-error { display: block; color: var(--theme-color-red-700); font-size: .8rem; }
        .emissions-link, .emissions-sort-link { color: var(--theme-color-blue-700); font-weight: 700; text-decoration: none; }
        .emissions-sort-link { align-items: center; display: inline-flex; gap: .3rem; color: inherit; }
        .emissions-sort-link[aria-current="true"] { color: var(--theme-color-blue-700); }
        .emissions-pagination { border-top: 1px solid var(--theme-color-gray-300); padding: 1rem 1.25rem; }
        .emissions-pagination .rainfall-pagination { align-items: center; display: grid; grid-template-columns: 1fr auto 1fr; width: 100%; }
        .emissions-pagination .rainfall-pagination__previous { justify-self: start; }
        .emissions-pagination .rainfall-pagination__pages { align-items: center; display: flex; gap: .25rem; justify-content: center; }
        .emissions-pagination .rainfall-pagination__next { justify-self: end; }
        .emissions-pagination .rainfall-pagination a, .emissions-pagination .rainfall-pagination span { align-items: center; border: 1px solid var(--theme-color-gray-300); border-radius: .35rem; color: var(--theme-color-gray-700); display: inline-flex; font-size: .82rem; font-weight: 600; height: 2rem; justify-content: center; min-width: 2rem; padding: 0 .45rem; text-decoration: none; }
        .emissions-pagination .rainfall-pagination .is-current { background: #2563eb; border-color: #2563eb; color: #fff; }
        .emissions-pagination .rainfall-pagination .is-gap { border-color: transparent; min-width: auto; }
        @media (max-width: 58rem) { .emissions-stats, .emissions-upload, .emissions-filters { grid-template-columns: 1fr; } .emissions-pagination .rainfall-pagination { grid-template-columns: 1fr; gap: .75rem; } .emissions-pagination .rainfall-pagination__previous, .emissions-pagination .rainfall-pagination__next { justify-self: center; } }
    </style>
@endpush

@section('content')
    <main class="emissions-workspace">
        <h1 class="emissions-heading">Emissions data</h1>
        <p class="emissions-intro">Stage historical emissions CSV files, approve validated imports, and correct individual published facts.</p>
        @if (session('status'))<p class="emissions-status" role="status">{{ session('status') }}</p>@endif

        <section class="emissions-stats" aria-label="Emissions dataset summary">
            <article class="emissions-stat"><p class="emissions-label">Source</p><p class="emissions-number">{{ $stats['sources'] ?: 'Not recorded' }}</p></article>
            <article class="emissions-stat"><p class="emissions-label">Fact records</p><p class="emissions-number">{{ number_format($stats['total_records']) }}</p></article>
            <article class="emissions-stat"><p class="emissions-label">Latest reporting year</p><p class="emissions-number">{{ $stats['latest_year'] ?? 'No records' }}</p></article>
        </section>

        <section class="emissions-panel" aria-labelledby="emissions-ingestion-heading">
            <header class="emissions-panel__header"><h2 id="emissions-ingestion-heading" class="emissions-panel__title">Ingest emissions CSV</h2><p class="emissions-copy">Use the wide CSV format: country, sector, gas, optional source, then one or more four-digit year columns.</p></header>
            <div class="emissions-panel__body">
                <form method="POST" action="{{ route('statamic.cp.climatehub.emissions.inject') }}" enctype="multipart/form-data" class="emissions-upload">
                    @csrf
                    <label class="emissions-field">CSV file<input name="emissions_csv" type="file" accept=".csv,text/csv" required></label>
                    <button class="emissions-button" type="submit">Stage CSV</button>
                </form>
                @error('emissions_csv')<span class="emissions-error">{{ $message }}</span>@enderror
            </div>
        </section>

        <section class="emissions-panel" aria-labelledby="emissions-imports-heading">
            <header class="emissions-panel__header"><h2 id="emissions-imports-heading" class="emissions-panel__title">Emissions import history</h2><p class="emissions-copy">The Redis worker validates a staged file. Only a fully validated import can be approved to incrementally upsert the historical series.</p></header>
            <div class="emissions-table-wrap"><table class="emissions-table"><thead><tr><th>File</th><th>Status</th><th>Validated facts</th><th>Completed</th><th><span class="sr-only">Approval</span></th></tr></thead><tbody>
                @forelse ($imports as $import)
                    <tr>
                        <td>
                            {{ basename($import->summary['path'] ?? 'Unknown file') }}
                            @php($duplicateMessage = $import->errors->firstWhere('error_code', 'duplicate_source_file')?->message)
                            @if ($duplicateMessage)<span class="emissions-import-duplicate">{{ $duplicateMessage }}</span>
                            @elseif ($import->duplicate_of_import_id ?? false)<span class="emissions-import-duplicate">Same file as import #{{ $import->duplicate_of_import_id }}</span>@endif
                        </td>
                        <td><span class="emissions-import-status">{{ str_replace('_', ' ', $import->status) }}</span></td>
                        <td>{{ number_format($import->accepted_rows) }} <span class="emissions-import-summary">accepted / {{ number_format($import->rejected_rows) }} rejected</span></td>
                        <td>{{ $import->completed_at?->format('Y-m-d H:i') ?? 'Processing' }}</td>
                        <td class="number">@if ($import->status === 'awaiting_approval')<form method="POST" action="{{ route('statamic.cp.climatehub.emissions.imports.approve', $import) }}">@csrf<button class="emissions-button emissions-button--secondary" type="submit">Approve</button></form>@endif</td>
                    </tr>
                @empty <tr><td colspan="5">No emissions files have been staged yet.</td></tr>
                @endforelse
            </tbody></table></div>
            @if ($imports->hasPages())<footer class="emissions-pagination">{{ $imports->links('cp.rainfall.pagination', ['ariaLabel' => 'Emissions import pagination']) }}</footer>@endif
            @error('emissions_import')<p class="emissions-error" style="padding: .85rem 1.25rem">{{ $message }}</p>@enderror
        </section>

        <section class="emissions-panel" aria-labelledby="emissions-facts-heading">
            <header class="emissions-panel__header"><h2 id="emissions-facts-heading" class="emissions-panel__title">Emissions facts</h2>
                <form method="GET" class="emissions-filters">
                    <label class="emissions-field">Country code<input name="country" value="{{ $filters['country'] ?? '' }}"></label>
                    <label class="emissions-field">Sector<select name="sector"><option value="">All sectors</option>@foreach ($sectors as $sector)<option value="{{ $sector->code }}" @selected(($filters['sector'] ?? null) === $sector->code)>{{ $sector->name }}</option>@endforeach</select></label>
                    <label class="emissions-field">Gas<select name="gas"><option value="">All gases</option>@foreach ($gases as $gas)<option value="{{ $gas->code }}" @selected(($filters['gas'] ?? null) === $gas->code)>{{ $gas->name }}</option>@endforeach</select></label>
                    <label class="emissions-field">From year<input name="year_from" type="number" min="1850" max="2100" value="{{ $filters['year_from'] ?? '' }}"></label>
                    <label class="emissions-field">To year<input name="year_to" type="number" min="1850" max="2100" value="{{ $filters['year_to'] ?? '' }}"></label>
                    <label class="emissions-field">Per page<select name="per_page">@foreach ([25, 50, 100] as $size)<option value="{{ $size }}" @selected($records->perPage() === $size)>{{ $size }}</option>@endforeach</select></label>
                    <button class="emissions-button emissions-button--secondary" type="submit">Filter</button>
                </form>
            </header>
            <div class="emissions-table-wrap"><table class="emissions-table"><thead><tr>
                @foreach (['country' => 'Country', 'year' => 'Year', 'sector' => 'Sector', 'gas' => 'Gas', 'value' => 'Value'] as $column => $label)
                    @php($nextDirection = $sort === $column && $direction === 'asc' ? 'desc' : 'asc')
                    <th @class(['number' => $column === 'value'])><a class="emissions-sort-link" href="{{ request()->fullUrlWithQuery(['sort' => $column, 'direction' => $nextDirection, 'page' => null]) }}" @if ($sort === $column) aria-current="true" @endif>{{ $label }} @if ($sort === $column) {!! $direction === 'asc' ? '&uarr;' : '&darr;' !!} @endif</a></th>
                @endforeach
                <th>Source</th><th><span class="sr-only">Edit</span></th>
            </tr></thead><tbody>
                @forelse ($records as $record)<tr><td><strong>{{ $record->country_code }}</strong></td><td>{{ $record->year }}</td><td>{{ $record->sector?->name }}</td><td>{{ $record->gas?->name }}</td><td class="number">{{ number_format((float) $record->value, 3) }}</td><td class="emissions-muted">{{ $record->source?->name }}</td><td class="number"><a class="emissions-link" href="{{ route('statamic.cp.climatehub.emissions.edit', $record) }}">Edit</a></td></tr>
                @empty <tr><td colspan="7">No emissions facts match these filters.</td></tr>
                @endforelse
            </tbody></table></div>
            <footer class="emissions-pagination">{{ $records->links('cp.rainfall.pagination', ['ariaLabel' => 'Emissions fact pagination']) }}</footer>
        </section>
    </main>
@endsection

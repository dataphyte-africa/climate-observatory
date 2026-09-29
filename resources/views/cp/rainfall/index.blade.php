@extends('statamic::layout')

@section('title', 'Rainfall data')

@push('head')
    <style>
        .rainfall-workspace { max-width: 1120px; margin: 0 auto; padding: 2rem 0 3rem; color: var(--theme-color-gray-900); }
        .rainfall-workspace h1, .rainfall-workspace h2, .rainfall-workspace p { margin: 0; }
        .rainfall-heading { margin-top: .4rem !important; font-size: 1.7rem; font-weight: 700; line-height: 1.2; }
        .rainfall-intro { margin-top: .65rem !important; color: var(--theme-color-gray-600); max-width: 46rem; }
        .rainfall-status { margin-top: 1.25rem; border: 1px solid var(--theme-color-green-300); border-radius: .5rem; background: var(--theme-color-green-50); color: var(--theme-color-green-800); padding: .8rem 1rem; font-size: .9rem; }
        .rainfall-stats { display: grid; gap: 1rem; grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top: 1.75rem; }
        .rainfall-stat, .rainfall-panel { border: 1px solid var(--theme-color-gray-300); border-radius: .6rem; background: var(--theme-color-content-bg); }
        .rainfall-stat { padding: 1rem 1.1rem; }
        .rainfall-label { color: var(--theme-color-gray-500); font-size: .73rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .rainfall-number { margin-top: .45rem !important; font-size: 1.45rem; font-weight: 700; }
        .rainfall-panel { margin-top: 1.5rem; overflow: hidden; }
        .rainfall-panel__header { padding: 1.1rem 1.25rem; border-bottom: 1px solid var(--theme-color-gray-300); }
        .rainfall-panel__title { font-size: 1.05rem; font-weight: 700; }
        .rainfall-panel__copy { margin-top: .35rem !important; color: var(--theme-color-gray-600); font-size: .9rem; }
        .rainfall-panel__body { padding: 1.25rem; }
        .rainfall-form { display: grid; gap: .9rem; margin-top: 1rem; }
        .rainfall-upload-form { grid-template-columns: minmax(0, 1fr) 12rem auto; align-items: end; }
        .rainfall-filter-form { grid-template-columns: minmax(11rem, 1.4fr) minmax(7rem, .9fr) minmax(8rem, .9fr) minmax(8rem, .9fr) 6.25rem auto; align-items: end; }
        .rainfall-field { display: grid; gap: .35rem; color: var(--theme-color-gray-700); font-size: .84rem; font-weight: 600; }
        .rainfall-field input, .rainfall-field select { width: 100%; min-height: 2.55rem; border: 1px solid var(--theme-color-gray-300); border-radius: .4rem; background: var(--theme-color-content-bg); padding: .45rem .65rem; color: var(--theme-color-gray-900); font: inherit; font-weight: 400; }
        .rainfall-button { min-height: 2.55rem; border: 1px solid #006d5b; border-radius: .4rem; background: #006d5b; color: #fff; cursor: pointer; padding: .45rem .9rem; font-size: .85rem; font-weight: 700; white-space: nowrap; }
        .rainfall-button:hover { background: #005748; border-color: #005748; }
        .rainfall-button--secondary { background: #fff; color: #006d5b; }
        .rainfall-button--secondary:hover { background: #edf7f4; color: #005748; }
        .rainfall-button:focus-visible { outline: 3px solid #93c5fd; outline-offset: 2px; }
        .rainfall-button:disabled { border-color: var(--theme-color-gray-300); background: var(--theme-color-gray-200); color: var(--theme-color-gray-500); cursor: not-allowed; }
        .rainfall-table-wrap { overflow-x: auto; }
        .rainfall-table { width: 100%; min-width: 720px; border-collapse: collapse; font-size: .9rem; }
        .rainfall-table th { background: var(--theme-color-gray-50); color: var(--theme-color-gray-600); padding: .8rem 1rem; text-align: left; font-size: .72rem; letter-spacing: .04em; text-transform: uppercase; }
        .rainfall-table td { border-top: 1px solid var(--theme-color-gray-200); padding: .85rem 1rem; vertical-align: middle; }
        .rainfall-table .number { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; }
        .rainfall-import-summary { color: var(--theme-color-gray-600); font-size: .83rem; }
        .rainfall-import-duplicate { display: block; margin-top: .3rem; color: var(--theme-color-amber-800); font-size: .78rem; font-weight: 700; }
        .rainfall-import-status { display: inline-flex; border-radius: 999px; background: var(--theme-color-gray-100); color: var(--theme-color-gray-700); padding: .2rem .5rem; font-size: .73rem; font-weight: 700; text-transform: capitalize; }
        .rainfall-muted { color: var(--theme-color-gray-500); font-size: .82rem; }
        .rainfall-link { color: var(--theme-color-blue-700); font-weight: 700; text-decoration: none; }
        .rainfall-footer { border-top: 1px solid var(--theme-color-gray-300); }
        .rainfall-pagination-row { padding: 1rem 1.25rem; }
        .rainfall-imports-footer { border-top: 1px solid var(--theme-color-gray-300); padding: 1rem 1.25rem; }
        .rainfall-pagination { align-items: center; display: grid; grid-template-columns: 1fr auto 1fr; width: 100%; }
        .rainfall-pagination__previous { justify-self: start; }
        .rainfall-pagination__pages { align-items: center; display: flex; gap: .25rem; justify-content: center; }
        .rainfall-pagination__next { justify-self: end; }
        .rainfall-pagination a, .rainfall-pagination span { align-items: center; border: 1px solid var(--theme-color-gray-300); border-radius: .35rem; color: var(--theme-color-gray-700); display: inline-flex; font-size: .82rem; font-weight: 600; height: 2rem; justify-content: center; min-width: 2rem; padding: 0 .45rem; text-decoration: none; }
        .rainfall-pagination .is-current { background: #2563eb; border-color: #2563eb; color: #fff; }
        .rainfall-pagination .is-gap { border-color: transparent; min-width: auto; }
        .rainfall-error { display: block; color: var(--theme-color-red-700); font-size: .8rem; }
        .rainfall-sort-link { align-items: center; color: inherit; display: inline-flex; gap: .3rem; text-decoration: none; }
        .rainfall-sort-link[aria-current="true"] { color: var(--theme-color-blue-700); }
        @media (max-width: 46rem) { .rainfall-stats, .rainfall-upload-form, .rainfall-filter-form { grid-template-columns: 1fr; } .rainfall-pagination { grid-template-columns: 1fr; gap: .75rem; } .rainfall-pagination__previous, .rainfall-pagination__next { justify-self: center; } }
    </style>
@endpush

@section('content')
    <main class="rainfall-workspace">
        <h1 class="rainfall-heading">Rainfall data</h1>
        <p class="rainfall-intro">Stage rainfall CSV files and correct canonical rainfall records with an audit trail.</p>

        @if (session('status'))
            <p class="rainfall-status" role="status">{{ session('status') }}</p>
        @endif

        <section class="rainfall-stats" aria-label="Rainfall dataset summary">
            <article class="rainfall-stat">
                <p class="rainfall-label">Source</p>
                <p class="rainfall-number">{{ $sourceName }}</p>
            </article>
            <article class="rainfall-stat">
                <p class="rainfall-label">Fact records</p>
                <p class="rainfall-number">{{ number_format($stats['total_records']) }}</p>
            </article>
            <article class="rainfall-stat">
                <p class="rainfall-label">Latest period</p>
                <p class="rainfall-number">{{ $stats['latest_period'] ?? 'No records' }}</p>
            </article>
        </section>

        <section class="rainfall-panel" aria-labelledby="rainfall-ingestion-heading">
            <header class="rainfall-panel__header">
                <h2 id="rainfall-ingestion-heading" class="rainfall-panel__title">Ingest rainfall CSV</h2>
                <p class="rainfall-panel__copy">Each validated source row incrementally updates the canonical rainfall records for this source.</p>
            </header>
            <div class="rainfall-panel__body">
                <form method="POST" action="{{ route('statamic.cp.climatehub.rainfall.inject') }}" enctype="multipart/form-data" class="rainfall-form rainfall-upload-form">
                    @csrf
                    <label class="rainfall-field">
                        CSV file
                        <input name="rainfall_csv" type="file" accept=".csv,text/csv" required>
                    </label>
                    <label class="rainfall-field">
                        Period type
                        <select name="period_type">
                            <option value="">Auto-detect</option>
                            <option value="dekad">10-day</option>
                            <option value="month">Monthly</option>
                            <option value="quarter">3-month</option>
                        </select>
                    </label>
                    <button type="submit" class="rainfall-button">Stage CSV</button>
                    @error('rainfall_csv')<span class="rainfall-error">{{ $message }}</span>@enderror
                </form>
            </div>
        </section>

        <section class="rainfall-panel" aria-labelledby="rainfall-imports-heading">
            <header class="rainfall-panel__header">
                <h2 id="rainfall-imports-heading" class="rainfall-panel__title">Rainfall import history</h2>
                <p class="rainfall-panel__copy">A fully validated file is approved here before its staged rows update the canonical rainfall records.</p>
            </header>
            <div class="rainfall-table-wrap">
                <table class="rainfall-table">
                    <thead><tr><th>File</th><th>Status</th><th>Validated rows</th><th>Completed</th><th><span class="sr-only">Approval</span></th></tr></thead>
                    <tbody>
                        @forelse ($imports as $import)
                            <tr>
                                <td>
                                    {{ basename($import->summary['path'] ?? 'Unknown file') }}
                                    @php($duplicateMessage = $import->errors->firstWhere('error_code', 'duplicate_source_file')?->message)
                                    @if ($duplicateMessage)
                                        <span class="rainfall-import-duplicate">{{ $duplicateMessage }}</span>
                                    @elseif ($import->duplicate_of_import_id ?? false)
                                        <span class="rainfall-import-duplicate">Same file as import #{{ $import->duplicate_of_import_id }}</span>
                                    @endif
                                </td>
                                <td><span class="rainfall-import-status">{{ str_replace('_', ' ', $import->status) }}</span></td>
                                <td>{{ number_format($import->accepted_rows) }} <span class="rainfall-import-summary">accepted / {{ number_format($import->rejected_rows) }} rejected</span></td>
                                <td>{{ $import->completed_at?->format('Y-m-d H:i') ?? 'Processing' }}</td>
                                <td class="number">
                                    @if ($import->status === 'awaiting_approval')
                                        <form method="POST" action="{{ route('statamic.cp.climatehub.rainfall.imports.approve', $import) }}">
                                            @csrf
                                            <button type="submit" class="rainfall-button">Approve</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No rainfall files have been staged yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($imports->hasPages())
                <footer class="rainfall-imports-footer">{{ $imports->links('cp.rainfall.pagination', ['ariaLabel' => 'Rainfall import pagination']) }}</footer>
            @endif
            @error('rainfall_import')<p class="rainfall-error" style="padding: .85rem 1.25rem">{{ $message }}</p>@enderror
        </section>

        <section class="rainfall-panel" aria-labelledby="rainfall-records-heading">
            <header class="rainfall-panel__header">
                <h2 id="rainfall-records-heading" class="rainfall-panel__title">Rainfall records</h2>
                <form method="GET" class="rainfall-form rainfall-filter-form">
                    <label class="rainfall-field">
                        Indicator
                        <select name="indicator">
                            <option value="">All indicators</option>
                            @foreach ($indicators as $indicator)
                                <option value="{{ $indicator->code }}" @selected(($filters['indicator'] ?? null) === $indicator->code)>{{ $indicator->code }} - {{ $indicator->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="rainfall-field">Geography code<input name="geography" value="{{ $filters['geography'] ?? '' }}"></label>
                    <label class="rainfall-field">From date<input name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></label>
                    <label class="rainfall-field">To date<input name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></label>
                    <label class="rainfall-field">
                        Per page
                        <select name="per_page">
                            @foreach ([25, 50, 100] as $size)
                                <option value="{{ $size }}" @selected($records->perPage() === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="rainfall-button rainfall-button--secondary">Filter</button>
                </form>
            </header>
            <div class="rainfall-table-wrap">
                <table class="rainfall-table">
                    <thead><tr>
                        @foreach (['period' => 'Period', 'geography' => 'Geography', 'indicator' => 'Indicator', 'value' => 'Value'] as $column => $label)
                            @php($nextDirection = $sort === $column && $direction === 'asc' ? 'desc' : 'asc')
                            <th @class(['number' => $column === 'value'])>
                                <a class="rainfall-sort-link" href="{{ request()->fullUrlWithQuery(['sort' => $column, 'direction' => $nextDirection, 'page' => null]) }}" @if ($sort === $column) aria-current="true" @endif>
                                    {{ $label }} @if ($sort === $column) {!! $direction === 'asc' ? '&uarr;' : '&darr;' !!} @endif
                                </a>
                            </th>
                        @endforeach
                        <th><span class="sr-only">Edit</span></th>
                    </tr></thead>
                    <tbody>
                        @forelse ($records as $record)
                            <tr>
                                <td>{{ $record->period_date?->toDateString() }} <span class="rainfall-muted">{{ $record->period_type }}</span></td>
                                <td><strong>{{ $record->geography_code }}</strong><span class="rainfall-muted"> {{ $record->geography?->name }}</span></td>
                                <td>{{ $record->indicator?->code }}</td>
                                <td class="number">{{ $record->value === null ? 'No value' : number_format((float) $record->value, 3) }}</td>
                                <td class="number"><a class="rainfall-link" href="{{ route('statamic.cp.climatehub.rainfall.edit', $record) }}">Edit</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No rainfall records match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <footer class="rainfall-footer">
                <div class="rainfall-pagination-row">{{ $records->links('cp.rainfall.pagination') }}</div>
            </footer>
        </section>
    </main>
@endsection

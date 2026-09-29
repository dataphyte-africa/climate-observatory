@extends('statamic::layout')

@section('title', 'Edit rainfall fact')

@push('head')
    <style>
        .rainfall-workspace { max-width: 760px; margin: 0 auto; padding: 2rem 0 3rem; color: var(--theme-color-gray-900); }
        .rainfall-workspace h1, .rainfall-workspace p { margin: 0; }
        .rainfall-eyebrow { color: var(--theme-color-blue-600); font-size: .75rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .rainfall-heading { margin-top: .4rem !important; font-size: 1.7rem; font-weight: 700; line-height: 1.2; }
        .rainfall-intro { margin-top: .65rem !important; color: var(--theme-color-gray-600); max-width: 46rem; }
        .rainfall-panel { margin-top: 1.5rem; overflow: hidden; border: 1px solid var(--theme-color-gray-300); border-radius: .6rem; background: var(--theme-color-content-bg); }
        .rainfall-panel__body { padding: 1.25rem; }
        .rainfall-form { display: grid; gap: .9rem; }
        .rainfall-filter-form { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .rainfall-field { display: grid; gap: .35rem; color: var(--theme-color-gray-700); font-size: .84rem; font-weight: 600; }
        .rainfall-field input, .rainfall-field select, .rainfall-field textarea { width: 100%; border: 1px solid var(--theme-color-gray-300); border-radius: .4rem; background: var(--theme-color-content-bg); padding: .55rem .65rem; color: var(--theme-color-gray-900); font: inherit; font-weight: 400; }
        .rainfall-button { min-height: 2.55rem; border: 1px solid var(--theme-color-blue-600); border-radius: .4rem; background: var(--theme-color-blue-600); color: white; cursor: pointer; padding: .45rem .9rem; font-size: .85rem; font-weight: 700; text-decoration: none; white-space: nowrap; }
        .rainfall-button--secondary { background: transparent; color: var(--theme-color-blue-700); }
        @media (max-width: 46rem) { .rainfall-filter-form { grid-template-columns: 1fr; } }
    </style>
@endpush

@section('content')
    <main class="rainfall-workspace">
        <p class="rainfall-eyebrow">Rainfall fact</p>
        <h1 class="rainfall-heading">Edit fact #{{ $rainfallValue->id }}</h1>
        <p class="rainfall-intro">Changes are recorded in the canonical rainfall record with a required audit reason.</p>

        <section class="rainfall-panel">
            <form method="POST" action="{{ route('statamic.cp.climatehub.rainfall.update', $rainfallValue) }}" class="rainfall-panel__body rainfall-form rainfall-filter-form">
                @csrf
                @method('PATCH')
                <label class="rainfall-field">Value<input name="value" type="number" step="0.000001" value="{{ old('value', $rainfallValue->value) }}"></label>
                <label class="rainfall-field">Period date<input name="period_date" type="date" value="{{ old('period_date', $rainfallValue->period_date?->toDateString()) }}" required></label>
                <label class="rainfall-field">Period type<input name="period_type" value="{{ old('period_type', $rainfallValue->period_type) }}" required></label>
                <label class="rainfall-field">Geography code<input name="geography_code" value="{{ old('geography_code', $rainfallValue->geography_code) }}" required></label>
                <label class="rainfall-field">
                    Indicator
                    <select name="indicator_id" required>
                        @foreach ($indicators as $indicator)
                            <option value="{{ $indicator->id }}" @selected((int) old('indicator_id', $rainfallValue->indicator_id) === $indicator->id)>{{ $indicator->code }} - {{ $indicator->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="rainfall-field" style="grid-column: 1 / -1;">Audit reason<textarea name="reason" rows="4" required>{{ old('reason') }}</textarea></label>
                <div style="grid-column: 1 / -1; display: flex; gap: .75rem;">
                    <button type="submit" class="rainfall-button">Save correction</button>
                    <a class="rainfall-button rainfall-button--secondary" href="{{ route('statamic.cp.climatehub.rainfall.index') }}">Cancel</a>
                </div>
            </form>
        </section>
    </main>
@endsection

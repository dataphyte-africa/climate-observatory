@extends('statamic::layout')

@section('title', 'Edit emissions fact')

@push('head')
    <style>
        .emissions-edit { max-width: 760px; margin: 0 auto; padding: 2rem 0 3rem; }
        .emissions-edit h1, .emissions-edit p { margin: 0; }
        .emissions-edit__intro { margin-top: .65rem !important; color: var(--theme-color-gray-600); }
        .emissions-edit__panel { margin-top: 1.5rem; padding: 1.25rem; border: 1px solid var(--theme-color-gray-300); border-radius: .6rem; background: var(--theme-color-content-bg); }
        .emissions-edit__form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        .emissions-edit label { display: grid; gap: .35rem; color: var(--theme-color-gray-700); font-size: .84rem; font-weight: 600; }
        .emissions-edit input, .emissions-edit select, .emissions-edit textarea { width: 100%; border: 1px solid var(--theme-color-gray-300); border-radius: .4rem; padding: .55rem .65rem; font: inherit; }
        .emissions-edit textarea, .emissions-edit__actions { grid-column: 1 / -1; }
        .emissions-edit button, .emissions-edit a { display: inline-block; border: 1px solid var(--theme-color-blue-600); border-radius: .4rem; background: var(--theme-color-blue-600); color: white; padding: .55rem .9rem; font-weight: 700; text-decoration: none; }
        .emissions-edit a { margin-left: .5rem; background: transparent; color: var(--theme-color-blue-700); }
        .emissions-edit__source { grid-column: 1 / -1; color: var(--theme-color-gray-600); }
        @media (max-width: 40rem) { .emissions-edit__form { grid-template-columns: 1fr; } }
    </style>
@endpush

@section('content')
    <main class="emissions-edit"><h1>Edit emissions fact</h1><p class="emissions-edit__intro">Correct a published value with an audit reason. Source provenance remains fixed to the imported source.</p>
        <section class="emissions-edit__panel"><form method="POST" action="{{ route('statamic.cp.climatehub.emissions.update', $emissionsValue) }}" class="emissions-edit__form">@csrf @method('PATCH')
            <p class="emissions-edit__source">Source: <strong>{{ $emissionsValue->source?->name ?? 'Not recorded' }}</strong></p>
            <label>Country code<input name="country_code" value="{{ old('country_code', $emissionsValue->country_code) }}" required></label>
            <label>Reporting year<input name="year" type="number" min="1850" max="2100" value="{{ old('year', $emissionsValue->year) }}" required></label>
            <label>Sector<select name="sector_id">@foreach ($sectors as $sector)<option value="{{ $sector->id }}" @selected(old('sector_id', $emissionsValue->sector_id) == $sector->id)>{{ $sector->name }}</option>@endforeach</select></label>
            <label>Gas<select name="gas_id">@foreach ($gases as $gas)<option value="{{ $gas->id }}" @selected(old('gas_id', $emissionsValue->gas_id) == $gas->id)>{{ $gas->name }}</option>@endforeach</select></label>
            <label>Value<input name="value" type="number" step="any" value="{{ old('value', $emissionsValue->value) }}" required></label>
            <label>Reason<textarea name="reason" rows="3" required>{{ old('reason') }}</textarea></label>
            <div class="emissions-edit__actions"><button type="submit">Save fact</button><a href="{{ route('statamic.cp.climatehub.emissions.index') }}">Cancel</a></div>
        </form></section>
    </main>
@endsection

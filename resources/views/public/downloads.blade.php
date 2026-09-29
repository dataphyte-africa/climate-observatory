@extends('layouts.dataset-browser', ['title' => 'Available climate data', 'activeNav' => 'downloads'])

@section('content')
    <main class="ch-shell py-8 md:py-10">
        <section class="border-b border-outline-variant pb-6">
            <h1 class="font-headline text-3xl font-semibold leading-tight text-primary">Available datasets</h1>
            <p class="mt-2 max-w-2xl text-base leading-7 text-on-surface-variant">Browse source-backed climate data by topic and source, then download the files you need.</p>
        </section>

        <section class="mt-5" aria-label="Available datasets">
            @include('public.partials.home-dataset-list', [
                'datasets' => $datasets,
                'datasetTopics' => $datasetTopics,
                'sortable' => true,
                'paginate' => true,
                'sort' => $sort,
                'direction' => $direction,
            ])
        </section>
    </main>
@endsection

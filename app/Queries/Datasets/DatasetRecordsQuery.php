<?php

namespace App\Queries\Datasets;

use App\Models\AdminPeriodIndicatorValue;
use App\Models\CountryDocument;
use App\Models\CountryDocumentSectorResponse;
use App\Models\CountryYearIndicatorValue;
use App\Models\CountryYearSectorGasValue;
use App\Models\Dataset;
use App\Models\DatasetVersion;
use App\Models\EventImpact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use LogicException;

class DatasetRecordsQuery
{
    public function resolveVersion(Request $request, Dataset $dataset): DatasetVersion
    {
        $query = $dataset->versions()
            ->whereIn('status', ['published', 'active'])
            ->orderByDesc('activated_at')
            ->orderByDesc('id');

        if ($request->filled('version')) {
            $query->where('version_label', $request->string('version')->toString());
        }

        $version = $query->first();

        abort_if(! $version, 404, 'Dataset version not found.');

        return $version;
    }

    public function forDataset(Dataset $dataset, int $versionId, Request $request): Builder
    {
        $query = match ($dataset->default_fact_table) {
            'country_year_sector_gas_values' => CountryYearSectorGasValue::query()
                ->with(['source', 'sector', 'gas'])
                ->where('dataset_version_id', $versionId)
                ->orderBy('country_code')
                ->orderBy('year'),
            'admin_period_indicator_values' => AdminPeriodIndicatorValue::query()
                ->with(['source', 'geography', 'indicator'])
                ->where('dataset_version_id', $versionId)
                ->orderByDesc('period_date')
                ->orderBy('geography_code'),
            'country_year_indicator_values' => CountryYearIndicatorValue::query()
                ->with(['source', 'indicator'])
                ->where('dataset_version_id', $versionId)
                ->orderBy('country_code')
                ->orderBy('year'),
            'country_documents' => CountryDocument::query()
                ->with(['source', 'documentType'])
                ->where('dataset_version_id', $versionId)
                ->orderByDesc('submission_date')
                ->orderBy('document_code'),
            'country_document_sector_responses' => CountryDocumentSectorResponse::query()
                ->with(['source', 'documentType', 'sector'])
                ->where('dataset_version_id', $versionId)
                ->orderBy('document_code')
                ->orderBy('question_code'),
            'event_impacts' => EventImpact::query()
                ->with(['source', 'geography', 'unit'])
                ->where('dataset_version_id', $versionId)
                ->orderByDesc('event_date')
                ->orderBy('geography_code'),
            default => throw new LogicException("Unsupported dataset fact table [{$dataset->default_fact_table}]."),
        };

        $this->applyFilters($query, $dataset->default_fact_table, $request);

        return $query;
    }

    private function applyFilters(Builder $query, string $table, Request $request): void
    {
        match ($table) {
            'country_year_sector_gas_values' => $this->applyCountryYearSectorGasFilters($query, $request),
            'admin_period_indicator_values' => $this->applyAdminPeriodIndicatorFilters($query, $request),
            'country_year_indicator_values' => $this->applyCountryYearIndicatorFilters($query, $request),
            'country_documents' => $this->applyCountryDocumentFilters($query, $request),
            'country_document_sector_responses' => $this->applyCountryDocumentResponseFilters($query, $request),
            'event_impacts' => $this->applyEventImpactFilters($query, $request),
            default => null,
        };
    }

    private function applyCountryYearSectorGasFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('country_code'), fn (Builder $q) => $q->where('country_code', $request->string('country_code')->upper()->toString()))
            ->when($request->filled('year'), fn (Builder $q) => $q->where('year', $request->integer('year')))
            ->when($request->filled('sector_code'), fn (Builder $q) => $q->whereRelation('sector', 'code', $request->string('sector_code')->toString()))
            ->when($request->filled('gas_code'), fn (Builder $q) => $q->whereRelation('gas', 'code', $request->string('gas_code')->toString()));
    }

    private function applyAdminPeriodIndicatorFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('geography_code'), fn (Builder $q) => $q->where('geography_code', $request->string('geography_code')->toString()))
            ->when($request->filled('indicator_code'), fn (Builder $q) => $q->whereRelation('indicator', 'code', $request->string('indicator_code')->toString()))
            ->when($request->filled('period_type'), fn (Builder $q) => $q->where('period_type', $request->string('period_type')->toString()))
            ->when($request->filled('date_from'), fn (Builder $q) => $q->whereDate('period_date', '>=', $request->string('date_from')->toString()))
            ->when($request->filled('date_to'), fn (Builder $q) => $q->whereDate('period_date', '<=', $request->string('date_to')->toString()));
    }

    private function applyCountryYearIndicatorFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('country_code'), fn (Builder $q) => $q->where('country_code', $request->string('country_code')->upper()->toString()))
            ->when($request->filled('year'), fn (Builder $q) => $q->where('year', $request->integer('year')))
            ->when($request->filled('indicator_code'), fn (Builder $q) => $q->whereRelation('indicator', 'code', $request->string('indicator_code')->toString()));
    }

    private function applyCountryDocumentFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('country_code'), fn (Builder $q) => $q->where('country_code', $request->string('country_code')->upper()->toString()))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('document_type_code'), fn (Builder $q) => $q->whereRelation('documentType', 'code', $request->string('document_type_code')->toString()));
    }

    private function applyCountryDocumentResponseFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('country_code'), fn (Builder $q) => $q->where('country_code', $request->string('country_code')->upper()->toString()))
            ->when($request->filled('document_code'), fn (Builder $q) => $q->where('document_code', $request->string('document_code')->toString()))
            ->when($request->filled('question_code'), fn (Builder $q) => $q->where('question_code', $request->string('question_code')->toString()))
            ->when($request->filled('sector_code'), fn (Builder $q) => $q->whereRelation('sector', 'code', $request->string('sector_code')->toString()))
            ->when($request->filled('document_type_code'), fn (Builder $q) => $q->whereRelation('documentType', 'code', $request->string('document_type_code')->toString()));
    }

    private function applyEventImpactFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('country_code'), fn (Builder $q) => $q->where('country_code', $request->string('country_code')->upper()->toString()))
            ->when($request->filled('geography_code'), fn (Builder $q) => $q->where('geography_code', $request->string('geography_code')->toString()))
            ->when($request->filled('event_type'), fn (Builder $q) => $q->where('event_type', $request->string('event_type')->toString()))
            ->when($request->filled('metric_code'), fn (Builder $q) => $q->where('metric_code', $request->string('metric_code')->toString()))
            ->when($request->filled('date_from'), fn (Builder $q) => $q->whereDate('event_date', '>=', $request->string('date_from')->toString()))
            ->when($request->filled('date_to'), fn (Builder $q) => $q->whereDate('event_date', '<=', $request->string('date_to')->toString()));
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Models\DatasetVersion;
use App\Queries\Datasets\DatasetCatalogQuery;
use App\Queries\Datasets\DatasetRecordsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatasetApiController extends Controller
{
    private const MAX_EXPORT_ROWS = 5000;

    public function __construct(
        private readonly DatasetCatalogQuery $catalogQuery,
        private readonly DatasetRecordsQuery $recordsQuery,
    ) {}

    public function index(): JsonResponse
    {
        $datasets = $this->catalogQuery->getApiVisible()
            ->map(fn (Dataset $dataset) => $this->serializeDataset($dataset));

        return response()->json([
            'data' => $datasets,
        ]);
    }

    public function show(Dataset $dataset): JsonResponse
    {
        $this->authorizePublicDataset($dataset);

        $dataset = $this->catalogQuery->getOne($dataset);

        return response()->json([
            'data' => $this->serializeDataset($dataset, true),
        ]);
    }

    public function records(Request $request, Dataset $dataset): JsonResponse
    {
        $this->authorizePublicDataset($dataset);

        $version = $this->recordsQuery->resolveVersion($request, $dataset);
        $table = $dataset->default_fact_table;
        $query = $this->recordsQuery->forDataset($dataset, $version->id, $request);

        $perPage = min(max($request->integer('per_page', 25), 1), 100);
        $paginator = $query->paginate($perPage)->withQueryString();
        $items = collect($paginator->items())
            ->map(fn ($record) => $this->serializeRecord($table, $record))
            ->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'dataset_code' => $dataset->code,
                'dataset_type' => $dataset->dataset_type,
                'fact_table' => $table,
                'version_label' => $version->version_label,
                'applied_filters' => $this->appliedFilters($request),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function exportJson(Request $request, Dataset $dataset): JsonResponse
    {
        $this->authorizePublicDataset($dataset);
        abort_unless($dataset->download_enabled, 403, 'Dataset downloads are disabled.');

        $version = $this->recordsQuery->resolveVersion($request, $dataset);
        $query = $this->recordsQuery->forDataset($dataset, $version->id, $request);
        $totalRows = (clone $query)->count();
        $records = $query
            ->limit(self::MAX_EXPORT_ROWS)
            ->get()
            ->map(fn ($record) => $this->serializeRecord($dataset->default_fact_table, $record))
            ->values();

        return response()->json([
            'meta' => $this->exportMetadata($request, $dataset, $version, $totalRows),
            'data' => $records,
        ]);
    }

    public function exportCsv(Request $request, Dataset $dataset): StreamedResponse
    {
        $this->authorizePublicDataset($dataset);
        abort_unless($dataset->download_enabled, 403, 'Dataset downloads are disabled.');

        $version = $this->recordsQuery->resolveVersion($request, $dataset);
        $query = $this->recordsQuery->forDataset($dataset, $version->id, $request);
        $totalRows = (clone $query)->count();
        $records = $query->limit(self::MAX_EXPORT_ROWS)->get();
        $headers = $this->recordHeaders($dataset->default_fact_table);
        $filename = "{$dataset->code}-{$version->version_label}.csv";

        $dataset->increment('download_count');

        return response()->streamDownload(function () use ($records, $headers, $dataset) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            foreach ($records as $record) {
                $row = $this->serializeRecord($dataset->default_fact_table, $record);

                fputcsv($output, array_map(
                    fn (string $header) => $this->csvValue($row[$header] ?? null),
                    $headers
                ));
            }

            fclose($output);
        }, $filename, $this->csvMetadataHeaders($request, $dataset, $version, $totalRows));
    }

    private function authorizePublicDataset(Dataset $dataset): void
    {
        abort_unless($dataset->status === 'active' && $dataset->api_enabled, 404);
    }

    private function serializeDataset(Dataset $dataset, bool $includeVersions = false): array
    {
        $latestVersion = $dataset->versions->first();

        $payload = [
            'code' => $dataset->code,
            'title' => $dataset->title,
            'slug' => $dataset->slug,
            'summary' => $dataset->summary,
            'dataset_type' => $dataset->dataset_type,
            'import_schema' => $dataset->import_schema,
            'fact_table' => $dataset->default_fact_table,
            'status' => $dataset->status,
            'api_enabled' => $dataset->api_enabled,
            'download_enabled' => $dataset->download_enabled,
            'download_count' => $dataset->download_count,
            'featured' => $dataset->featured,
            'source' => $dataset->source ? [
                'code' => $dataset->source->code,
                'name' => $dataset->source->name,
            ] : null,
            'latest_version' => $latestVersion ? [
                'label' => $latestVersion->version_label,
                'status' => $latestVersion->status,
                'row_count' => $latestVersion->row_count,
                'activated_at' => optional($latestVersion->activated_at)->toISOString(),
            ] : null,
        ];

        if ($includeVersions) {
            $payload['versions'] = $dataset->versions->map(fn (DatasetVersion $version) => [
                'label' => $version->version_label,
                'status' => $version->status,
                'row_count' => $version->row_count,
                'activated_at' => optional($version->activated_at)->toISOString(),
                'import_started_at' => optional($version->import_started_at)->toISOString(),
                'import_completed_at' => optional($version->import_completed_at)->toISOString(),
            ])->values();
        }

        return $payload;
    }

    private function exportMetadata(Request $request, Dataset $dataset, DatasetVersion $version, int $totalRows): array
    {
        return [
            'dataset_code' => $dataset->code,
            'dataset_title' => $dataset->title,
            'dataset_type' => $dataset->dataset_type,
            'schema' => $dataset->import_schema,
            'fact_table' => $dataset->default_fact_table,
            'version_label' => $version->version_label,
            'version_status' => $version->status,
            'row_count' => $version->row_count,
            'exported_rows' => min($totalRows, self::MAX_EXPORT_ROWS),
            'total_matching_rows' => $totalRows,
            'truncated' => $totalRows > self::MAX_EXPORT_ROWS,
            'max_export_rows' => self::MAX_EXPORT_ROWS,
            'import_completed_at' => optional($version->import_completed_at)->toISOString(),
            'activated_at' => optional($version->activated_at)->toISOString(),
            'source' => $dataset->source ? [
                'code' => $dataset->source->code,
                'name' => $dataset->source->name,
                'organization_name' => $dataset->source->organization_name,
                'website_url' => $dataset->source->website_url,
                'license_name' => $dataset->source->license_name,
                'license_url' => $dataset->source->license_url,
                'citation' => $dataset->source->citation_template,
            ] : null,
            'applied_filters' => $this->appliedFilters($request),
        ];
    }

    private function csvMetadataHeaders(Request $request, Dataset $dataset, DatasetVersion $version, int $totalRows): array
    {
        return [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Dataset-Code' => $dataset->code,
            'X-Dataset-Version' => $version->version_label,
            'X-Dataset-Schema' => (string) $dataset->import_schema,
            'X-Dataset-Fact-Table' => (string) $dataset->default_fact_table,
            'X-Dataset-Source' => (string) $dataset->source?->name,
            'X-Dataset-License' => (string) $dataset->source?->license_name,
            'X-Export-Total-Matching-Rows' => (string) $totalRows,
            'X-Export-Truncated' => $totalRows > self::MAX_EXPORT_ROWS ? 'true' : 'false',
            'X-Export-Applied-Filters' => json_encode($this->appliedFilters($request), JSON_THROW_ON_ERROR),
        ];
    }

    private function appliedFilters(Request $request): array
    {
        return collect([
            'version',
            'country_code',
            'year',
            'sector_code',
            'gas_code',
            'geography_code',
            'indicator_code',
            'period_type',
            'date_from',
            'date_to',
            'status',
            'document_type_code',
            'document_code',
            'question_code',
            'event_type',
            'metric_code',
        ])
            ->filter(fn (string $filter) => $request->filled($filter))
            ->mapWithKeys(fn (string $filter) => [$filter => $request->input($filter)])
            ->all();
    }

    private function recordHeaders(string $table): array
    {
        return match ($table) {
            'country_year_sector_gas_values' => [
                'country_code',
                'year',
                'value',
                'source_code',
                'sector_code',
                'sector_name',
                'gas_code',
                'gas_name',
                'metadata',
            ],
            'admin_period_indicator_values' => [
                'geography_code',
                'geography_name',
                'indicator_code',
                'indicator_name',
                'period_date',
                'period_type',
                'value',
                'metadata',
            ],
            'country_year_indicator_values' => [
                'country_code',
                'indicator_code',
                'indicator_name',
                'year',
                'value',
                'metadata',
            ],
            'country_documents' => [
                'country_code',
                'document_code',
                'document_type_code',
                'document_type_name',
                'title',
                'submission_date',
                'status',
                'summary',
                'payload',
            ],
            'country_document_sector_responses' => [
                'country_code',
                'document_code',
                'document_type_code',
                'sector_code',
                'sector_name',
                'question_code',
                'subsector_code',
                'response_text',
                'metadata',
            ],
            'event_impacts' => [
                'country_code',
                'geography_code',
                'geography_name',
                'event_date',
                'event_type',
                'event_name',
                'metric_code',
                'unit_code',
                'value',
                'metadata',
            ],
            default => throw new LogicException("Unsupported dataset fact table [{$table}]."),
        };
    }

    private function csvValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function serializeRecord(string $table, mixed $record): array
    {
        return match ($table) {
            'country_year_sector_gas_values' => [
                'country_code' => $record->country_code,
                'year' => $record->year,
                'value' => $record->value,
                'source_code' => $record->source?->code,
                'sector_code' => $record->sector?->code,
                'sector_name' => $record->sector?->name,
                'gas_code' => $record->gas?->code,
                'gas_name' => $record->gas?->name,
                'metadata' => $record->metadata,
            ],
            'admin_period_indicator_values' => [
                'geography_code' => $record->geography_code,
                'geography_name' => $record->geography?->name,
                'indicator_code' => $record->indicator?->code,
                'indicator_name' => $record->indicator?->name,
                'period_date' => $record->period_date?->toDateString(),
                'period_type' => $record->period_type,
                'value' => $record->value,
                'metadata' => $record->metadata,
            ],
            'country_year_indicator_values' => [
                'country_code' => $record->country_code,
                'indicator_code' => $record->indicator?->code,
                'indicator_name' => $record->indicator?->name,
                'year' => $record->year,
                'value' => $record->value,
                'metadata' => $record->metadata,
            ],
            'country_documents' => [
                'country_code' => $record->country_code,
                'document_code' => $record->document_code,
                'document_type_code' => $record->documentType?->code,
                'document_type_name' => $record->documentType?->name,
                'title' => $record->title,
                'submission_date' => optional($record->submission_date)->toDateString(),
                'status' => $record->status,
                'summary' => $record->summary,
                'payload' => $record->payload,
            ],
            'country_document_sector_responses' => [
                'country_code' => $record->country_code,
                'document_code' => $record->document_code,
                'document_type_code' => $record->documentType?->code,
                'sector_code' => $record->sector?->code,
                'sector_name' => $record->sector?->name,
                'question_code' => $record->question_code,
                'subsector_code' => $record->subsector_code,
                'response_text' => $record->response_text,
                'metadata' => $record->metadata,
            ],
            'event_impacts' => [
                'country_code' => $record->country_code,
                'geography_code' => $record->geography_code,
                'geography_name' => $record->geography?->name,
                'event_date' => $record->event_date?->toDateString(),
                'event_type' => $record->event_type,
                'event_name' => $record->event_name,
                'metric_code' => $record->metric_code,
                'unit_code' => $record->unit?->code,
                'value' => $record->value,
                'metadata' => $record->metadata,
            ],
            default => throw new LogicException("Unsupported dataset fact table [{$table}]."),
        };
    }
}

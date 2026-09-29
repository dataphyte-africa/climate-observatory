<?php

namespace App\Queries\Maps;

use App\Models\AdminPeriodIndicatorValue;
use App\Models\Dataset;
use App\Models\DatasetVersion;
use App\Models\Geography;
use App\Models\GeographyShape;
use App\Models\Indicator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RainfallMapQuery
{
    public const GEOMETRY_CACHE_SECONDS = 3600;

    public const DATA_CACHE_SECONDS = 300;

    public function geometry(int $level, Request $request): array
    {
        $filters = $this->geometryFilters($level, $request);
        $shapes = $this->shapeQuery($filters)->get();

        return [
            'type' => 'FeatureCollection',
            'features' => $shapes
                ->map(fn (GeographyShape $shape) => $this->feature($shape, true))
                ->values()
                ->all(),
            'meta' => $this->geometryMeta($filters, $shapes->count()),
        ];
    }

    public function children(string $code, Request $request): array
    {
        $parent = Geography::query()
            ->where('code', $code)
            ->firstOrFail();

        $level = $parent->level + 1;
        $filters = $this->geometryFilters($level, $request, $parent->code);
        $children = Geography::query()
            ->with(['parent', 'shapes' => fn ($query) => $query
                ->where('boundary_version', $filters['boundary_version'])
                ->where('geometry_status', $filters['geometry_status'])
                ->where('simplification', $filters['simplification'])])
            ->where('parent_id', $parent->id)
            ->where('level', $level)
            ->orderBy('name')
            ->get();

        return [
            'data' => $children->map(fn (Geography $child) => [
                'code' => $child->code,
                'name' => $child->name,
                'level' => $child->level,
                'parent_code' => $parent->code,
                'parent_name' => $parent->name,
                'shape_available' => $child->shapes->isNotEmpty(),
                'geometry' => $this->optionalChildGeometry($child, $request),
            ])->values()->all(),
            'meta' => [
                'parent_code' => $parent->code,
                'parent_name' => $parent->name,
                ...$this->geometryMeta($filters, $children->count()),
            ],
        ];
    }

    public function values(Dataset $dataset, Request $request): array
    {
        $context = $this->valueContext($dataset, $request);
        $values = $this->valueRows($context);

        return [
            'data' => $values->values()->all(),
            'meta' => $this->valueMeta($context, $values),
        ];
    }

    public function choropleth(Dataset $dataset, Request $request): array
    {
        $context = $this->valueContext($dataset, $request);
        $filters = $this->geometryFilters($context['geography_level'], $request, $context['parent_code']);
        $shapes = $this->shapeQuery($filters)->get();
        $values = $this->valueRows($context)->keyBy('geography_code');
        $comparisonValues = $context['comparison_indicator']
            ? $this->valueRows([...$context, 'indicator' => $context['comparison_indicator']])->keyBy('geography_code')
            : collect();
        $includeGeometry = $request->boolean('include_geometry', true);

        return [
            'type' => 'FeatureCollection',
            'features' => $shapes
                ->map(fn (GeographyShape $shape) => $this->choroplethFeature($shape, $values, $comparisonValues, $includeGeometry))
                ->values()
                ->all(),
            'legend' => $this->legendPayload($context, $values->values()),
            'tooltip_fields' => [
                'geography_code',
                'geography_name',
                'parent_code',
                'parent_name',
                'value',
                'unit',
                'period_date',
                'source',
                'boundary_version',
                'geometry_status',
                'value_status',
            ],
            'quality' => $this->qualityPayload($context, $shapes, $values),
            'meta' => [
                ...$this->valueMeta($context, $values->values()),
                'include_geometry' => $includeGeometry,
                'response_truncated' => false,
            ],
        ];
    }

    public function legend(Dataset $dataset, Request $request): array
    {
        $context = $this->valueContext($dataset, $request);
        $values = $this->valueRows($context);

        return [
            'data' => $this->legendPayload($context, $values),
            'meta' => $this->valueMeta($context, $values),
        ];
    }

    public function quality(Dataset $dataset, Request $request): array
    {
        $context = $this->valueContext($dataset, $request);
        $filters = $this->geometryFilters($context['geography_level'], $request, $context['parent_code']);
        $shapes = $this->shapeQuery($filters)->get();
        $values = $this->valueRows($context)->keyBy('geography_code');

        return [
            'data' => $this->qualityPayload($context, $shapes, $values),
            'meta' => [
                ...$this->valueMeta($context, $values->values()),
                'scope' => $request->query('scope', 'all'),
            ],
        ];
    }

    public function assertPublicDataset(Dataset $dataset): Dataset
    {
        abort_unless($dataset->status === 'active' && $dataset->api_enabled, 404);
        abort_unless($dataset->default_fact_table === 'admin_period_indicator_values', 422, 'Only admin-period indicator datasets are supported by the public map API.');

        return $dataset->loadMissing('source');
    }

    private function geometryFilters(int $level, Request $request, ?string $parentCode = null): array
    {
        abort_unless(in_array($level, [1, 2], true), 422, 'Only admin-1 and admin-2 geometry are supported.');

        $geometryStatus = $request->query('geometry_status', 'published');

        abort_unless($geometryStatus === 'published', 422, 'Public map APIs expose only published geometry.');

        return [
            'level' => $level,
            'boundary_version' => (string) $request->query('boundary_version', 'v01'),
            'geometry_status' => 'published',
            'simplification' => (string) $request->query('simplification', 'overview'),
            'parent_code' => $parentCode ?? $request->query('parent_code'),
        ];
    }

    private function shapeQuery(array $filters)
    {
        return GeographyShape::query()
            ->with(['geography.parent'])
            ->where('boundary_version', $filters['boundary_version'])
            ->where('geometry_status', $filters['geometry_status'])
            ->where('simplification', $filters['simplification'])
            ->where('geography_level', $filters['level'])
            ->when($filters['parent_code'], fn ($query) => $query->whereHas(
                'geography.parent',
                fn ($parentQuery) => $parentQuery->where('code', $filters['parent_code'])
            ))
            ->whereNotNull('geometry_geojson')
            ->orderBy(
                Geography::query()
                    ->select('code')
                    ->whereColumn('geographies.id', 'geography_shapes.geography_id')
                    ->limit(1)
            );
    }

    private function valueContext(Dataset $dataset, Request $request): array
    {
        $this->assertPublicDataset($dataset);

        $level = $request->integer('geography_level', 1);
        abort_unless(in_array($level, [1, 2], true), 422, 'Only admin-1 and admin-2 values are supported.');

        $indicatorCode = $request->query('indicator_code');
        abort_unless(is_string($indicatorCode) && $indicatorCode !== '', 422, 'indicator_code is required for map values.');

        $indicator = Indicator::query()
            ->with('unit')
            ->where('code', $indicatorCode)
            ->firstOrFail();
        $comparisonIndicatorCode = $request->query('comparison_indicator_code');
        abort_if($comparisonIndicatorCode !== null && ! is_string($comparisonIndicatorCode), 422, 'Invalid comparison_indicator_code parameter.');
        $comparisonIndicator = $comparisonIndicatorCode
            ? Indicator::query()->with('unit')->where('code', $comparisonIndicatorCode)->firstOrFail()
            : null;

        $version = $this->resolveVersion($dataset, $request);
        $parentCode = $request->query('parent_code');
        abort_if($parentCode !== null && ! is_string($parentCode), 422, 'Invalid parent_code parameter.');

        return [
            'dataset' => $dataset,
            'version' => $version,
            'indicator' => $indicator,
            'comparison_indicator' => $comparisonIndicator,
            'geography_level' => $level,
            'parent_code' => $parentCode,
            'period_type' => $request->query('period_type'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'boundary_version' => (string) $request->query('boundary_version', 'v01'),
            'aggregation' => (string) $request->query('aggregation', 'latest'),
        ];
    }

    private function resolveVersion(Dataset $dataset, Request $request): DatasetVersion
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

    private function valueRows(array $context): Collection
    {
        $aggregation = $context['aggregation'];
        abort_unless(in_array($aggregation, ['latest', 'sum', 'avg', 'min', 'max'], true), 422, 'Unsupported map aggregation.');

        $codes = $this->geographyCodes($context);
        $base = AdminPeriodIndicatorValue::query()
            ->where('dataset_version_id', $context['version']->id)
            ->where('indicator_id', $context['indicator']->id)
            ->whereIn('geography_code', $codes)
            ->when($context['period_type'], fn ($query) => $query->where('period_type', $context['period_type']))
            ->when($context['date_from'], fn ($query) => $query->whereDate('period_date', '>=', $context['date_from']))
            ->when($context['date_to'], fn ($query) => $query->whereDate('period_date', '<=', $context['date_to']));

        if ($aggregation === 'latest') {
            $latest = (clone $base)
                ->select('geography_code', DB::raw('MAX(period_date) as latest_period_date'))
                ->groupBy('geography_code');

            return AdminPeriodIndicatorValue::query()
                ->select('admin_period_indicator_values.geography_code', 'admin_period_indicator_values.period_date', 'admin_period_indicator_values.value', 'admin_period_indicator_values.metadata')
                ->joinSub($latest->toBase(), 'latest_values', function ($join) {
                    $join->on('admin_period_indicator_values.geography_code', '=', 'latest_values.geography_code')
                        ->on('admin_period_indicator_values.period_date', '=', 'latest_values.latest_period_date');
                })
                ->where('admin_period_indicator_values.dataset_version_id', $context['version']->id)
                ->where('admin_period_indicator_values.indicator_id', $context['indicator']->id)
                ->whereIn('admin_period_indicator_values.geography_code', $codes)
                ->when($context['period_type'], fn ($query) => $query->where('admin_period_indicator_values.period_type', $context['period_type']))
                ->when($context['date_from'], fn ($query) => $query->whereDate('admin_period_indicator_values.period_date', '>=', $context['date_from']))
                ->when($context['date_to'], fn ($query) => $query->whereDate('admin_period_indicator_values.period_date', '<=', $context['date_to']))
                ->orderBy('admin_period_indicator_values.geography_code')
                ->get()
                ->map(fn (AdminPeriodIndicatorValue $row) => $this->valuePayload($row, $context, 'value'));
        }

        $function = strtoupper($aggregation);

        return $base
            ->select('geography_code', DB::raw("{$function}(value) as value"), DB::raw('MAX(period_date) as period_date'))
            ->groupBy('geography_code')
            ->orderBy('geography_code')
            ->get()
            ->map(fn (AdminPeriodIndicatorValue $row) => $this->valuePayload($row, $context, 'value'));
    }

    private function geographyCodes(array $context): array
    {
        return Geography::query()
            ->where('level', $context['geography_level'])
            ->when($context['parent_code'], fn ($query) => $query->whereHas(
                'parent',
                fn ($parentQuery) => $parentQuery->where('code', $context['parent_code'])
            ))
            ->pluck('code')
            ->all();
    }

    private function valuePayload(AdminPeriodIndicatorValue $row, array $context, string $status): array
    {
        return [
            'geography_code' => $row->geography_code,
            'indicator_code' => $context['indicator']->code,
            'value' => $row->value !== null ? (float) $row->value : null,
            'value_status' => $status,
            'unit' => $context['indicator']->unit?->symbol ?? $context['indicator']->unit?->code,
            'period_date' => optional($row->period_date)->toDateString(),
            'source_status' => data_get($row->metadata, 'source_status'),
            'aggregation' => $context['aggregation'],
        ];
    }

    private function feature(GeographyShape $shape, bool $includeGeometry): array
    {
        $properties = [
            'geography_code' => $shape->geography->code,
            'geography_name' => $shape->geography->name,
            'geography_level' => $shape->geography_level,
            'parent_code' => $shape->geography->parent?->code,
            'parent_name' => $shape->geography->parent?->name,
            'boundary_version' => $shape->boundary_version,
            'geometry_status' => $shape->geometry_status,
            'simplification' => $shape->simplification,
            'bbox' => $shape->bbox,
        ];

        if ($shape->centroid_latitude !== null || $shape->centroid_longitude !== null) {
            $properties['centroid'] = [
                'latitude' => $shape->centroid_latitude !== null ? (float) $shape->centroid_latitude : null,
                'longitude' => $shape->centroid_longitude !== null ? (float) $shape->centroid_longitude : null,
            ];
        }

        return [
            'type' => 'Feature',
            'geometry' => $includeGeometry ? json_decode($shape->geometry_geojson, true, flags: JSON_THROW_ON_ERROR) : null,
            'properties' => $properties,
        ];
    }

    private function choroplethFeature(GeographyShape $shape, Collection $values, Collection $comparisonValues, bool $includeGeometry): array
    {
        $feature = $this->feature($shape, $includeGeometry);
        $value = $values->get($shape->geography->code);
        $comparison = $comparisonValues->get($shape->geography->code);

        $feature['properties'] = [
            ...$feature['properties'],
            'value' => $value['value'] ?? null,
            'value_status' => $value ? 'value' : 'no_value',
            'unit' => $value['unit'] ?? null,
            'period_date' => $value['period_date'] ?? null,
            'comparison_value' => $comparison['value'] ?? null,
            'comparison_unit' => $comparison['unit'] ?? null,
        ];

        return $feature;
    }

    private function optionalChildGeometry(Geography $child, Request $request): ?array
    {
        if (! $request->boolean('include_geometry', false)) {
            return null;
        }

        $shape = $child->shapes->first();

        return $shape?->geometry_geojson ? json_decode($shape->geometry_geojson, true, flags: JSON_THROW_ON_ERROR) : null;
    }

    private function qualityPayload(array $context, EloquentCollection $shapes, Collection $values): array
    {
        $geometryCodes = collect($shapes->map(fn (GeographyShape $shape) => $shape->geography->code)->all());
        $valueCodes = $values->keys();
        $unmatched = $valueCodes->diff($geometryCodes)->values();
        $noValue = $geometryCodes->diff($valueCodes)->values();

        return [
            'requested_level' => $context['geography_level'],
            'boundary_version' => $context['boundary_version'],
            'dataset_code' => $context['dataset']->code,
            'dataset_version' => $context['version']->version_label,
            'aggregation' => $context['aggregation'],
            'geometry_count' => $geometryCodes->count(),
            'value_count' => $valueCodes->count(),
            'unmatched_fact_pcode_count' => $unmatched->count(),
            'unmatched_fact_pcodes' => $unmatched->all(),
            'geometry_without_value_count' => $noValue->count(),
            'geometry_without_value_pcodes' => $noValue->all(),
            'excluded_rows' => 0,
            'truncated' => false,
        ];
    }

    private function legendPayload(array $context, Collection $values): array
    {
        $numeric = $values->pluck('value')->filter(fn ($value) => $value !== null)->values();

        if ($numeric->isEmpty()) {
            $bins = [];
        } else {
            $min = (float) $numeric->min();
            $max = (float) $numeric->max();
            $step = $max > $min ? ($max - $min) / 5 : 1;
            $bins = collect(range(0, 4))->map(fn (int $index) => [
                'min' => round($min + ($step * $index), 6),
                'max' => round($index === 4 ? $max : $min + ($step * ($index + 1)), 6),
                'label' => $index === 4
                    ? '>='.round($min + ($step * $index), 2)
                    : round($min + ($step * $index), 2).' - '.round($min + ($step * ($index + 1)), 2),
            ])->all();
        }

        return [
            'indicator_code' => $context['indicator']->code,
            'indicator_name' => $context['indicator']->name,
            'unit' => $context['indicator']->unit?->symbol ?? $context['indicator']->unit?->code,
            'color_scale' => 'rainfall-default',
            'bins' => $bins,
            'missing_label' => 'No value',
            'source' => [
                'code' => $context['dataset']->source?->code,
                'name' => $context['dataset']->source?->name,
                'citation' => $context['dataset']->source?->citation_template,
            ],
        ];
    }

    private function geometryMeta(array $filters, int $count): array
    {
        return [
            'geography_level' => $filters['level'],
            'boundary_version' => $filters['boundary_version'],
            'geometry_status' => $filters['geometry_status'],
            'simplification' => $filters['simplification'],
            'parent_code' => $filters['parent_code'],
            'feature_count' => $count,
            'cache_seconds' => self::GEOMETRY_CACHE_SECONDS,
        ];
    }

    private function valueMeta(array $context, Collection $values): array
    {
        return [
            'dataset_code' => $context['dataset']->code,
            'dataset_title' => $context['dataset']->title,
            'dataset_version' => $context['version']->version_label,
            'geography_level' => $context['geography_level'],
            'indicator_code' => $context['indicator']->code,
            'period_type' => $context['period_type'],
            'date_from' => $context['date_from'],
            'date_to' => $context['date_to'],
            'aggregation' => $context['aggregation'],
            'value_count' => $values->count(),
            'cache_seconds' => self::DATA_CACHE_SECONDS,
            'source' => [
                'code' => $context['dataset']->source?->code,
                'name' => $context['dataset']->source?->name,
                'citation' => $context['dataset']->source?->citation_template,
            ],
        ];
    }
}

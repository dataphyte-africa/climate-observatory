<?php

namespace App\Queries\Datasets;

use App\Models\DocumentType;
use App\Models\Gas;
use App\Models\Geography;
use App\Models\Indicator;
use App\Models\Sector;
use App\Models\Source;
use Illuminate\Database\Eloquent\Builder;

class DatasetLookupQuery
{
    private const HIGH_CARDINALITY_TYPES = [
        'geographies',
        'indicators',
    ];

    private const DATABASE_TYPES = [
        'sources',
        'sectors',
        'gases',
        'indicators',
        'geographies',
        'document_types',
    ];

    private const TYPES = [
        'dataset_types',
        'sources',
        'sectors',
        'gases',
        'indicators',
        'geographies',
        'document_types',
    ];

    public function supports(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    public function get(?string $type = null, int $page = 1, int $perPage = 100): array
    {
        $types = $type ? [$type] : self::TYPES;
        $data = [];
        $sections = [];

        foreach ($types as $lookupType) {
            if (in_array($lookupType, self::DATABASE_TYPES, true)) {
                $section = $this->paginatedSection($lookupType, $page, $perPage);
                $data[$lookupType] = $section['data'];
                $sections[$lookupType] = $section['meta'];

                continue;
            }

            $data[$lookupType] = $this->unpaginatedSection($lookupType);
            $sections[$lookupType] = [
                'paginated' => false,
                'total' => count($data[$lookupType]),
            ];
        }

        return [
            'data' => $data,
            'meta' => [
                'cache_seconds' => 300,
                'default_per_page' => 100,
                'max_per_page' => 250,
                'type' => $type,
                'sections' => $sections,
            ],
        ];
    }

    private function unpaginatedSection(string $type): array
    {
        return match ($type) {
            'dataset_types' => [
                ['code' => 'country_year_sector_gas', 'name' => 'Country-Year Sector Gas'],
                ['code' => 'admin_period_indicator', 'name' => 'Admin Period Indicator'],
                ['code' => 'country_year_indicator', 'name' => 'Country-Year Indicator'],
                ['code' => 'country_document', 'name' => 'Country Document'],
                ['code' => 'document_sector_response', 'name' => 'Document Sector Response'],
                ['code' => 'event_impact', 'name' => 'Event Impact'],
            ],
            default => [],
        };
    }

    private function paginatedSection(string $type, int $page, int $perPage): array
    {
        $query = $this->paginatedQuery($type);
        $total = (clone $query)->count();
        $rows = $query
            ->forPage($page, $perPage)
            ->get($this->columnsFor($type))
            ->map(fn ($row) => $row->attributesToArray())
            ->all();

        return [
            'data' => $rows,
            'meta' => [
                'paginated' => true,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
                'truncated' => $total > count($rows),
            ],
        ];
    }

    private function paginatedQuery(string $type): Builder
    {
        return match ($type) {
            'document_types' => DocumentType::query()->orderBy('name'),
            'gases' => Gas::query()->orderBy('name'),
            'indicators' => Indicator::query()->orderBy('name'),
            'geographies' => Geography::query()->orderBy('level')->orderBy('name'),
            'sectors' => Sector::query()->orderBy('name'),
            'sources' => Source::query()->orderBy('name'),
        };
    }

    private function columnsFor(string $type): array
    {
        return match ($type) {
            'document_types',
            'gases',
            'sectors',
            'sources' => ['code', 'name'],
            'indicators' => ['code', 'name', 'grain'],
            'geographies' => ['code', 'name', 'country_code', 'level', 'type'],
        };
    }
}

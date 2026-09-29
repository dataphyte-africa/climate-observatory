<?php

namespace App\Services\Geographies;

use App\Models\Geography;
use App\Models\GeographyAlias;
use App\Models\GeographyShape;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BoundaryGeoJsonImporter
{
    /**
     * @return array{boundary_version: string, level: int, scanned_features: int, imported_geographies: int, imported_shapes: int, errors: array<int, array<string, mixed>>}
     */
    public function preview(string $path, int $level, array $options = []): array
    {
        DB::beginTransaction();

        try {
            return $this->import($path, $level, $options);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @return array{boundary_version: string, level: int, scanned_features: int, imported_geographies: int, imported_shapes: int, errors: array<int, array<string, mixed>>}
     */
    public function import(string $path, int $level, array $options = []): array
    {
        if (! in_array($level, [1, 2], true)) {
            throw new InvalidArgumentException('Only admin-1 and admin-2 GeoJSON imports are supported in this slice.');
        }

        $payload = $this->readGeoJson($path);
        $features = $payload['features'] ?? null;

        if (! is_array($features)) {
            throw new InvalidArgumentException('GeoJSON payload must include a features array.');
        }

        $boundaryVersion = (string) ($options['boundary_version'] ?? $this->firstVersion($features) ?? 'unknown');
        $simplification = (string) ($options['simplification'] ?? 'overview');
        $sourceName = (string) ($options['source_name'] ?? 'boundary-fixture');
        $sourcePath = (string) ($options['source_path'] ?? $this->relativePath($path));
        $now = now();
        $errors = [];
        $importedGeographies = 0;
        $importedShapes = 0;

        $country = $this->countryRecord($boundaryVersion);

        foreach ($features as $index => $feature) {
            $properties = $feature['properties'] ?? [];
            $geometry = $feature['geometry'] ?? null;
            $code = $this->featureCode($properties, $level);

            if (! $code || ! is_array($geometry)) {
                $errors[] = [
                    'feature_index' => $index,
                    'code' => $code,
                    'message' => 'Feature is missing required PCODE or geometry.',
                ];

                continue;
            }

            $parent = $this->parentFor($properties, $level, $country, $boundaryVersion);
            $validOn = $this->dateValue($properties['valid_on'] ?? null);

            $geography = Geography::updateOrCreate(
                ['code' => $code],
                [
                    'source_pcode' => $code,
                    'name' => $this->featureName($properties, $level) ?? $code,
                    'country_code' => 'NGA',
                    'level' => $level,
                    'parent_id' => $parent?->id,
                    'type' => 'administrative',
                    'boundary_version' => $boundaryVersion,
                    'valid_from' => $validOn,
                    'valid_to' => $this->dateValue($properties['valid_to'] ?? null),
                    'alternate_codes' => $this->alternateCodes($properties, $level),
                    'metadata' => [
                        'source_name' => $sourceName,
                        'source_layer' => (string) ($options['source_layer'] ?? "admin{$level}"),
                        'source_version' => $properties['version'] ?? null,
                    ],
                    'updated_at' => $now,
                ]
            );

            $importedGeographies++;
            $this->upsertAliases($geography, $properties, $level, $sourceName);

            GeographyShape::updateOrCreate(
                [
                    'geography_id' => $geography->id,
                    'boundary_version' => $boundaryVersion,
                    'simplification' => $simplification,
                ],
                [
                    'geography_level' => $level,
                    'geometry_status' => (string) ($options['geometry_status'] ?? 'draft'),
                    'source_path' => $sourcePath,
                    'source_layer' => (string) ($options['source_layer'] ?? "admin{$level}"),
                    'source_feature_id' => $code,
                    'geometry_geojson' => json_encode($geometry, JSON_THROW_ON_ERROR),
                    'centroid_latitude' => $this->numberValue($properties['center_lat'] ?? null),
                    'centroid_longitude' => $this->numberValue($properties['center_lon'] ?? null),
                    'bbox' => $feature['bbox'] ?? $this->geometryBbox($geometry),
                    'area_sq_km' => $this->numberValue($properties['area_sqkm'] ?? null),
                    'metadata' => [
                        'source_name' => $sourceName,
                        'valid_on' => $properties['valid_on'] ?? null,
                        'source_version' => $properties['version'] ?? null,
                    ],
                ]
            );

            $importedShapes++;
        }

        return [
            'boundary_version' => $boundaryVersion,
            'level' => $level,
            'scanned_features' => count($features),
            'imported_geographies' => $importedGeographies,
            'imported_shapes' => $importedShapes,
            'errors' => $errors,
        ];
    }

    private function readGeoJson(string $path): array
    {
        if (! File::exists($path)) {
            throw new InvalidArgumentException("Boundary file [{$path}] does not exist.");
        }

        $payload = json_decode(File::get($path), true);

        if (! is_array($payload)) {
            throw new InvalidArgumentException("Boundary file [{$path}] is not valid GeoJSON.");
        }

        return $payload;
    }

    private function firstVersion(array $features): ?string
    {
        foreach ($features as $feature) {
            $version = $feature['properties']['version'] ?? null;

            if ($version) {
                return (string) $version;
            }
        }

        return null;
    }

    private function featureCode(array $properties, int $level): ?string
    {
        return $this->stringValue($properties["adm{$level}_pcode"] ?? null);
    }

    private function featureName(array $properties, int $level): ?string
    {
        return $this->stringValue($properties["adm{$level}_name"] ?? null);
    }

    private function parentFor(array $properties, int $level, Geography $country, string $boundaryVersion): ?Geography
    {
        if ($level === 1) {
            return $country;
        }

        $parentCode = $this->stringValue($properties['adm1_pcode'] ?? null);

        if (! $parentCode) {
            return null;
        }

        return Geography::firstOrCreate(
            ['code' => $parentCode],
            [
                'source_pcode' => $parentCode,
                'name' => $this->stringValue($properties['adm1_name'] ?? null) ?? $parentCode,
                'country_code' => 'NGA',
                'level' => 1,
                'parent_id' => $country->id,
                'type' => 'administrative',
                'boundary_version' => $boundaryVersion,
            ]
        );
    }

    private function countryRecord(string $boundaryVersion): Geography
    {
        $country = Geography::query()
            ->where('code', 'NG')
            ->orWhere(function ($query) {
                $query
                    ->where('code', 'NGA')
                    ->where('level', 0);
            })
            ->orderByRaw("case when code = 'NG' then 0 else 1 end")
            ->first() ?? new Geography;

        $country->forceFill([
            'code' => 'NG',
            'source_pcode' => 'NG',
            'name' => 'Nigeria',
            'country_code' => 'NGA',
            'level' => 0,
            'type' => 'country',
            'boundary_version' => $boundaryVersion,
        ])->save();

        $this->mergeLegacyCountryRecord($country);

        return $country;
    }

    private function mergeLegacyCountryRecord(Geography $country): void
    {
        $legacy = Geography::query()
            ->where('code', 'NGA')
            ->where('level', 0)
            ->first();

        if (! $legacy || $legacy->is($country)) {
            return;
        }

        Geography::query()
            ->where('parent_id', $legacy->id)
            ->update(['parent_id' => $country->id]);

        GeographyAlias::query()
            ->where('geography_id', $legacy->id)
            ->update(['geography_id' => $country->id]);

        GeographyShape::query()
            ->where('geography_id', $legacy->id)
            ->update(['geography_id' => $country->id]);

        $legacy->delete();
    }

    private function upsertAliases(Geography $geography, array $properties, int $level, string $sourceName): void
    {
        $aliases = array_filter([
            'pcode' => $this->featureCode($properties, $level),
            'name' => $this->featureName($properties, $level),
            'reference_name' => $this->stringValue($properties["adm{$level}_ref_name"] ?? null),
        ]);

        foreach ($aliases as $type => $value) {
            GeographyAlias::updateOrCreate(
                [
                    'source_name' => $sourceName,
                    'alias_type' => $type,
                    'normalized_alias' => Str::of($value)->lower()->squish()->toString(),
                ],
                [
                    'geography_id' => $geography->id,
                    'alias_value' => $value,
                    'metadata' => ['boundary_import' => true],
                ]
            );
        }
    }

    private function alternateCodes(array $properties, int $level): array
    {
        return array_filter([
            'adm0_pcode' => $this->stringValue($properties['adm0_pcode'] ?? null),
            'adm1_pcode' => $level === 2 ? $this->stringValue($properties['adm1_pcode'] ?? null) : null,
            'sendistpcode' => $this->stringValue($properties['sendistpcode'] ?? null),
        ]);
    }

    private function dateValue(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function numberValue(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }

    private function relativePath(string $path): string
    {
        return Str::of($path)
            ->replace(base_path().'/', '')
            ->toString();
    }

    /**
     * @return array<int, float>|null
     */
    private function geometryBbox(array $geometry): ?array
    {
        $coordinates = $geometry['coordinates'] ?? null;

        if (! is_array($coordinates)) {
            return null;
        }

        $bounds = [INF, INF, -INF, -INF];
        $this->expandBounds($coordinates, $bounds);

        if ($bounds[0] === INF) {
            return null;
        }

        return $bounds;
    }

    private function expandBounds(array $coordinates, array &$bounds): void
    {
        if (count($coordinates) >= 2 && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) {
            $lon = (float) $coordinates[0];
            $lat = (float) $coordinates[1];
            $bounds[0] = min($bounds[0], $lon);
            $bounds[1] = min($bounds[1], $lat);
            $bounds[2] = max($bounds[2], $lon);
            $bounds[3] = max($bounds[3], $lat);

            return;
        }

        foreach ($coordinates as $child) {
            if (is_array($child)) {
                $this->expandBounds($child, $bounds);
            }
        }
    }
}

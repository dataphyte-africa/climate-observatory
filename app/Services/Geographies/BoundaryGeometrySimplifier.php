<?php

namespace App\Services\Geographies;

use App\Models\GeographyShape;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BoundaryGeometrySimplifier
{
    /**
     * @return array<string, mixed>
     */
    public function preview(array $options = []): array
    {
        DB::beginTransaction();

        try {
            return $this->simplify($options);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function simplify(array $options = []): array
    {
        $boundaryVersion = (string) ($options['boundary_version'] ?? 'v01');
        $sourceSimplification = (string) ($options['source_simplification'] ?? 'overview');
        $targetSimplification = (string) ($options['target_simplification'] ?? 'public-1mb');
        $sourceStatus = (string) ($options['source_status'] ?? 'published');
        $targetStatus = (string) ($options['target_status'] ?? 'draft');
        $levels = $this->levels($options['levels'] ?? [1, 2]);
        $tolerance = $this->positiveFloat($options['tolerance'] ?? 0.0002, 'tolerance');
        $precision = $this->positiveInt($options['precision'] ?? 6, 'precision');

        if ($targetSimplification === $sourceSimplification) {
            throw new InvalidArgumentException('Target simplification must differ from source simplification.');
        }

        $summary = [
            'boundary_version' => $boundaryVersion,
            'source_simplification' => $sourceSimplification,
            'target_simplification' => $targetSimplification,
            'source_status' => $sourceStatus,
            'target_status' => $targetStatus,
            'tolerance' => $tolerance,
            'precision' => $precision,
            'levels' => [],
        ];

        foreach ($levels as $level) {
            $sourceShapes = GeographyShape::query()
                ->where('boundary_version', $boundaryVersion)
                ->where('simplification', $sourceSimplification)
                ->where('geometry_status', $sourceStatus)
                ->where('geography_level', $level)
                ->whereNotNull('geometry_geojson')
                ->orderBy('geography_id')
                ->get();

            $levelSummary = [
                'source_count' => $sourceShapes->count(),
                'target_count' => 0,
                'source_geometry_bytes' => 0,
                'target_geometry_bytes' => 0,
                'source_points' => 0,
                'target_points' => 0,
            ];

            foreach ($sourceShapes as $sourceShape) {
                $sourceGeometry = json_decode($sourceShape->geometry_geojson, true, flags: JSON_THROW_ON_ERROR);
                $targetGeometry = $this->simplifyGeometry($sourceGeometry, $tolerance, $precision);
                $targetGeometryJson = json_encode($targetGeometry, JSON_THROW_ON_ERROR);

                GeographyShape::updateOrCreate(
                    [
                        'geography_id' => $sourceShape->geography_id,
                        'boundary_version' => $boundaryVersion,
                        'simplification' => $targetSimplification,
                    ],
                    [
                        'geography_level' => $sourceShape->geography_level,
                        'geometry_status' => $targetStatus,
                        'source_path' => $sourceShape->source_path,
                        'source_layer' => $sourceShape->source_layer,
                        'source_feature_id' => $sourceShape->source_feature_id,
                        'geometry_geojson' => $targetGeometryJson,
                        'centroid_latitude' => $sourceShape->centroid_latitude,
                        'centroid_longitude' => $sourceShape->centroid_longitude,
                        'bbox' => $sourceShape->bbox,
                        'area_sq_km' => $sourceShape->area_sq_km,
                        'metadata' => [
                            ...($sourceShape->metadata ?? []),
                            'derived_from_simplification' => $sourceSimplification,
                            'derived_from_status' => $sourceStatus,
                            'simplification_tolerance' => $tolerance,
                            'coordinate_precision' => $precision,
                        ],
                    ]
                );

                $levelSummary['target_count']++;
                $levelSummary['source_geometry_bytes'] += strlen($sourceShape->geometry_geojson);
                $levelSummary['target_geometry_bytes'] += strlen($targetGeometryJson);
                $levelSummary['source_points'] += $this->pointCount($sourceGeometry);
                $levelSummary['target_points'] += $this->pointCount($targetGeometry);
            }

            $summary['levels'][$level] = $levelSummary;
        }

        return $summary;
    }

    /**
     * @return array<int, int>
     */
    private function levels(mixed $levels): array
    {
        if (is_string($levels)) {
            $levels = explode(',', $levels);
        }

        if (! is_array($levels)) {
            throw new InvalidArgumentException('Levels must be an array or comma-separated list.');
        }

        $values = array_values(array_unique(array_map('intval', $levels)));
        sort($values);

        foreach ($values as $level) {
            if (! in_array($level, [1, 2], true)) {
                throw new InvalidArgumentException('Only admin-1 and admin-2 simplification are supported.');
            }
        }

        return $values;
    }

    private function positiveFloat(mixed $value, string $name): float
    {
        if (! is_numeric($value) || (float) $value <= 0) {
            throw new InvalidArgumentException("{$name} must be a positive number.");
        }

        return (float) $value;
    }

    private function positiveInt(mixed $value, string $name): int
    {
        if (! is_numeric($value) || (int) $value < 1) {
            throw new InvalidArgumentException("{$name} must be a positive integer.");
        }

        return (int) $value;
    }

    /**
     * @param  array<string, mixed>  $geometry
     * @return array<string, mixed>
     */
    private function simplifyGeometry(array $geometry, float $tolerance, int $precision): array
    {
        return match ($geometry['type'] ?? null) {
            'Point' => [
                ...$geometry,
                'coordinates' => $this->roundPoint($geometry['coordinates'], $precision),
            ],
            'MultiPoint' => [
                ...$geometry,
                'coordinates' => array_map(
                    fn (array $point): array => $this->roundPoint($point, $precision),
                    $geometry['coordinates'] ?? []
                ),
            ],
            'LineString' => [
                ...$geometry,
                'coordinates' => $this->simplifyLine($geometry['coordinates'], $tolerance, $precision),
            ],
            'MultiLineString', 'Polygon' => [
                ...$geometry,
                'coordinates' => array_map(
                    fn (array $line): array => $this->simplifyRingOrLine($line, $tolerance, $precision),
                    $geometry['coordinates'] ?? []
                ),
            ],
            'MultiPolygon' => [
                ...$geometry,
                'coordinates' => array_map(
                    fn (array $polygon): array => array_map(
                        fn (array $ring): array => $this->simplifyRingOrLine($ring, $tolerance, $precision),
                        $polygon
                    ),
                    $geometry['coordinates'] ?? []
                ),
            ],
            'GeometryCollection' => [
                ...$geometry,
                'geometries' => array_map(
                    fn (array $child): array => $this->simplifyGeometry($child, $tolerance, $precision),
                    $geometry['geometries'] ?? []
                ),
            ],
            default => $geometry,
        };
    }

    /**
     * @param  array<int, array<int, mixed>>  $coordinates
     * @return array<int, array<int, float>>
     */
    private function simplifyRingOrLine(array $coordinates, float $tolerance, int $precision): array
    {
        if (count($coordinates) < 3) {
            return $this->simplifyLine($coordinates, $tolerance, $precision);
        }

        $closed = $this->samePoint($coordinates[0], $coordinates[count($coordinates) - 1]);

        if (! $closed) {
            return $this->simplifyLine($coordinates, $tolerance, $precision);
        }

        $open = array_slice($coordinates, 0, -1);
        $simplified = $this->douglasPeucker($open, $tolerance);

        if (count($simplified) < 3) {
            $simplified = array_slice($open, 0, 3);
        }

        $rounded = array_map(fn (array $point): array => $this->roundPoint($point, $precision), $simplified);
        $rounded[] = $rounded[0];

        return $rounded;
    }

    /**
     * @param  array<int, array<int, mixed>>  $coordinates
     * @return array<int, array<int, float>>
     */
    private function simplifyLine(array $coordinates, float $tolerance, int $precision): array
    {
        if (count($coordinates) <= 2) {
            return array_map(fn (array $point): array => $this->roundPoint($point, $precision), $coordinates);
        }

        return array_map(
            fn (array $point): array => $this->roundPoint($point, $precision),
            $this->douglasPeucker($coordinates, $tolerance)
        );
    }

    /**
     * @param  array<int, array<int, mixed>>  $points
     * @return array<int, array<int, mixed>>
     */
    private function douglasPeucker(array $points, float $tolerance): array
    {
        $count = count($points);

        if ($count <= 2) {
            return $points;
        }

        $maxDistance = 0.0;
        $index = 0;

        for ($i = 1; $i < $count - 1; $i++) {
            $distance = $this->perpendicularDistance($points[$i], $points[0], $points[$count - 1]);

            if ($distance > $maxDistance) {
                $index = $i;
                $maxDistance = $distance;
            }
        }

        if ($maxDistance > $tolerance) {
            $left = $this->douglasPeucker(array_slice($points, 0, $index + 1), $tolerance);
            $right = $this->douglasPeucker(array_slice($points, $index), $tolerance);

            return array_merge(array_slice($left, 0, -1), $right);
        }

        return [$points[0], $points[$count - 1]];
    }

    /**
     * @param  array<int, mixed>  $point
     * @param  array<int, mixed>  $start
     * @param  array<int, mixed>  $end
     */
    private function perpendicularDistance(array $point, array $start, array $end): float
    {
        $x = (float) $point[0];
        $y = (float) $point[1];
        $x1 = (float) $start[0];
        $y1 = (float) $start[1];
        $x2 = (float) $end[0];
        $y2 = (float) $end[1];

        $dx = $x2 - $x1;
        $dy = $y2 - $y1;

        if ($dx === 0.0 && $dy === 0.0) {
            return sqrt((($x - $x1) ** 2) + (($y - $y1) ** 2));
        }

        return abs(($dy * $x) - ($dx * $y) + ($x2 * $y1) - ($y2 * $x1)) / sqrt(($dy ** 2) + ($dx ** 2));
    }

    /**
     * @param  array<int, mixed>  $point
     * @return array<int, float>
     */
    private function roundPoint(array $point, int $precision): array
    {
        return [
            round((float) $point[0], $precision),
            round((float) $point[1], $precision),
        ];
    }

    /**
     * @param  array<int, mixed>  $first
     * @param  array<int, mixed>  $second
     */
    private function samePoint(array $first, array $second): bool
    {
        return (float) $first[0] === (float) $second[0]
            && (float) $first[1] === (float) $second[1];
    }

    /**
     * @param  array<string, mixed>  $geometry
     */
    private function pointCount(array $geometry): int
    {
        return match ($geometry['type'] ?? null) {
            'Point' => 1,
            'MultiPoint', 'LineString' => count($geometry['coordinates'] ?? []),
            'MultiLineString', 'Polygon' => array_sum(array_map(
                fn (array $line): int => count($line),
                $geometry['coordinates'] ?? []
            )),
            'MultiPolygon' => array_sum(array_map(
                fn (array $polygon): int => array_sum(array_map(fn (array $ring): int => count($ring), $polygon)),
                $geometry['coordinates'] ?? []
            )),
            'GeometryCollection' => array_sum(array_map(
                fn (array $child): int => $this->pointCount($child),
                $geometry['geometries'] ?? []
            )),
            default => 0,
        };
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Queries\Maps\RainfallMapQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MapApiController extends Controller
{
    private const KILOBYTE = 1024;

    private const MEGABYTE = 1048576;

    public function __construct(
        private readonly RainfallMapQuery $maps,
    ) {}

    public function geometry(Request $request, int $level): JsonResponse
    {
        return $this->cachedResponse(
            $request,
            "api.maps.geometry.{$level}",
            RainfallMapQuery::GEOMETRY_CACHE_SECONDS,
            $this->geometryLimit($request, $level),
            fn () => $this->maps->geometry($level, $request)
        );
    }

    public function values(Request $request, Dataset $dataset): JsonResponse
    {
        return $this->cachedResponse(
            $request,
            "api.maps.values.{$dataset->code}",
            RainfallMapQuery::DATA_CACHE_SECONDS,
            512 * self::KILOBYTE,
            fn () => $this->maps->values($dataset, $request)
        );
    }

    public function choropleth(Request $request, Dataset $dataset): JsonResponse
    {
        return $this->cachedResponse(
            $request,
            "api.maps.choropleth.{$dataset->code}",
            RainfallMapQuery::DATA_CACHE_SECONDS,
            $this->choroplethLimit($request),
            fn () => $this->maps->choropleth($dataset, $request)
        );
    }

    public function children(Request $request, string $code): JsonResponse
    {
        $includeGeometry = $request->boolean('include_geometry', false);

        return $this->cachedResponse(
            $request,
            "api.maps.children.{$code}",
            $includeGeometry ? RainfallMapQuery::GEOMETRY_CACHE_SECONDS : RainfallMapQuery::DATA_CACHE_SECONDS,
            $includeGeometry ? self::MEGABYTE : 256 * self::KILOBYTE,
            fn () => $this->maps->children($code, $request)
        );
    }

    public function legend(Request $request, Dataset $dataset): JsonResponse
    {
        return $this->cachedResponse(
            $request,
            "api.maps.legend.{$dataset->code}",
            RainfallMapQuery::DATA_CACHE_SECONDS,
            64 * self::KILOBYTE,
            fn () => $this->maps->legend($dataset, $request)
        );
    }

    public function quality(Request $request, Dataset $dataset): JsonResponse
    {
        return $this->cachedResponse(
            $request,
            "api.maps.quality.{$dataset->code}",
            RainfallMapQuery::DATA_CACHE_SECONDS,
            512 * self::KILOBYTE,
            fn () => $this->maps->quality($dataset, $request)
        );
    }

    private function cachedResponse(Request $request, string $keyPrefix, int $seconds, int $maxBytes, callable $callback): JsonResponse
    {
        foreach ($request->query() as $key => $value) {
            abort_if(is_array($value), 422, "Invalid {$key} parameter.");
        }

        $cacheKey = $keyPrefix.'.'.sha1(json_encode($request->query(), JSON_THROW_ON_ERROR));
        $payload = Cache::remember($cacheKey, $seconds, function () use ($callback, $maxBytes) {
            $payload = $callback();

            $this->assertPayloadWithinLimit($payload, $maxBytes);

            return $payload;
        });
        $payloadBytes = $this->assertPayloadWithinLimit($payload, $maxBytes);

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age='.$seconds.', stale-while-revalidate='.$seconds)
            ->header('X-Cache-TTL', (string) $seconds)
            ->header('X-Payload-Bytes', (string) $payloadBytes)
            ->header('X-Payload-Max-Bytes', (string) $maxBytes);
    }

    private function assertPayloadWithinLimit(array $payload, int $maxBytes): int
    {
        $bytes = strlen(json_encode($payload, JSON_THROW_ON_ERROR));

        abort_if($bytes > $maxBytes, 413, 'Map API response exceeds the accepted payload-size ceiling. Narrow the request with parent_code, include_geometry=false, simplification, or pagination.');

        return $bytes;
    }

    private function choroplethLimit(Request $request): int
    {
        if (! $request->boolean('include_geometry', true)) {
            return 768 * self::KILOBYTE;
        }

        return $this->geometryLimit($request, $request->integer('geography_level', 1));
    }

    private function geometryLimit(Request $request, int $level): int
    {
        if ($level === 1) {
            return self::MEGABYTE;
        }

        if ($level === 2 && $request->filled('parent_code')) {
            return self::MEGABYTE;
        }

        return 8 * self::MEGABYTE;
    }
}

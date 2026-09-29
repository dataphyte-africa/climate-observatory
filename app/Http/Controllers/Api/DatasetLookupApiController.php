<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Queries\Datasets\DatasetLookupQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DatasetLookupApiController extends Controller
{
    private const CACHE_SECONDS = 300;

    private const DEFAULT_PER_PAGE = 100;

    private const MAX_PER_PAGE = 250;

    public function __construct(
        private readonly DatasetLookupQuery $lookupQuery,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $type = $request->query('type');

        abort_if(
            $type !== null && (! is_string($type) || ! $this->lookupQuery->supports($type)),
            422,
            'Unsupported lookup type.'
        );

        $page = $this->positiveIntegerQuery($request, 'page', 1);
        $perPage = min($this->positiveIntegerQuery($request, 'per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);
        $cacheKey = 'api.lookups.'.($type ?? 'all').".{$page}.{$perPage}";

        $payload = Cache::remember(
            $cacheKey,
            self::CACHE_SECONDS,
            fn () => $this->lookupQuery->get(is_string($type) ? $type : null, $page, $perPage)
        );

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age='.self::CACHE_SECONDS.', stale-while-revalidate='.self::CACHE_SECONDS)
            ->header('X-Cache-TTL', (string) self::CACHE_SECONDS);
    }

    private function positiveIntegerQuery(Request $request, string $key, int $default): int
    {
        $value = $request->query($key, $default);

        abort_if(! is_scalar($value), 422, "Invalid {$key} parameter.");

        return max((int) $value, 1);
    }
}

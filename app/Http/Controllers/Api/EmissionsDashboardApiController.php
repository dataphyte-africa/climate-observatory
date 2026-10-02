<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CountryYearSectorGasValue;
use App\Models\Dataset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EmissionsDashboardApiController extends Controller
{
    private const CACHE_SECONDS = 900;

    public function __invoke(Request $request): JsonResponse
    {
        $dataset = Dataset::query()
            ->with(['versions' => fn ($query) => $query->orderByDesc('activated_at')->orderByDesc('id')])
            ->where('code', 'climatewatch_historical_emissions')
            ->where('status', 'active')
            ->firstOrFail();
        $version = $dataset->versions->firstOrFail();
        $gas = strtolower(trim((string) $request->query('gas')));
        $yearFrom = (int) $request->query('year_from');
        $yearTo = (int) $request->query('year_to', $yearFrom);

        abort_if($gas === '' || $yearFrom < 1800 || $yearTo < $yearFrom || ($yearTo - $yearFrom) > 200, 422, 'A valid gas and reporting-year range are required.');

        $filters = [
            'gas' => $gas,
            'gases' => $this->identifiers($request->query('gases')) ?: [$gas],
            'sector' => strtolower(trim((string) $request->query('sector', ''))),
            'countries' => $this->countryCodes($request->query('countries')),
            'sectors' => $this->identifiers($request->query('sectors')),
            'year_from' => $yearFrom,
            'year_to' => $yearTo,
        ];
        $versionTimestamp = $version->updated_at?->getTimestamp() ?? 0;
        $cacheKey = 'emissions.dashboard.records.'.$version->id.'.'.$versionTimestamp.'.'.sha1(json_encode($filters, JSON_THROW_ON_ERROR));

        $payload = Cache::remember($cacheKey, self::CACHE_SECONDS, function () use ($version, $filters): array {
            $records = CountryYearSectorGasValue::query()
                ->with(['sector:id,code', 'gas:id,code'])
                ->where('dataset_version_id', $version->id)
                ->whereBetween('year', [$filters['year_from'], $filters['year_to']])
                ->whereHas('gas', fn ($query) => $query->whereIn('code', $filters['gases']))
                ->when($filters['sector'] !== '', fn ($query) => $query->whereHas('sector', fn ($sector) => $sector->where('code', $filters['sector'])))
                ->when($filters['countries'] !== [], fn ($query) => $query->whereIn('country_code', $filters['countries']))
                ->when($filters['sectors'] !== [], fn ($query) => $query->whereHas('sector', fn ($sector) => $sector->whereIn('code', $filters['sectors'])))
                ->orderBy('country_code')
                ->orderBy('year')
                ->get(['country_year_sector_gas_values.id', 'country_code', 'sector_id', 'gas_id', 'year', 'value'])
                ->map(fn (CountryYearSectorGasValue $row): array => [
                    'country' => $row->country_code,
                    'sector' => $row->sector?->code,
                    'gas' => $row->gas?->code,
                    'year' => (int) $row->year,
                    'value' => (float) $row->value,
                ])
                ->values()
                ->all();

            return ['records' => $records];
        });

        return response()
            ->json($payload)
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate');
    }

    /** @return list<string> */
    private function countryCodes(mixed $value): array
    {
        $values = is_array($value) ? $value : (is_string($value) && $value !== '' ? explode(',', $value) : []);

        return collect($values)
            ->filter(fn ($code): bool => is_scalar($code))
            ->map(fn ($code): string => strtoupper(trim((string) $code)))
            ->filter(fn (string $code): bool => preg_match('/^[A-Z0-9_]{2,32}$/', $code) === 1)
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function identifiers(mixed $value): array
    {
        $values = is_array($value) ? $value : (is_string($value) && $value !== '' ? explode(',', $value) : []);

        return collect($values)
            ->filter(fn ($identifier): bool => is_scalar($identifier))
            ->map(fn ($value): string => strtolower(trim((string) $value)))
            ->filter(fn (string $value): bool => preg_match('/^[a-z0-9_]{2,32}$/', $value) === 1)
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }
}

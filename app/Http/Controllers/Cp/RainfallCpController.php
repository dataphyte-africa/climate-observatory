<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Imports\CsvDatasetImporter;
use App\Imports\Exceptions\CsvImportException;
use App\Jobs\ApplyApprovedCsvDatasetImport;
use App\Models\AdminPeriodIndicatorValue;
use App\Models\AdminPeriodIndicatorValueEdit;
use App\Models\Dataset;
use App\Models\DatasetImport;
use App\Models\DatasetVersion;
use App\Models\Geography;
use App\Models\Indicator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RainfallCpController extends Controller
{
    public function index(Request $request): View
    {
        $version = $this->rainfallDatasetVersion();
        $validated = $request->validate([
            'indicator' => ['nullable', 'string', 'max:100'],
            'geography' => ['nullable', 'string', 'max:64'],
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
            'sort' => ['nullable', 'in:period,geography,indicator,value'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 25);
        $sort = $validated['sort'] ?? 'period';
        $direction = $validated['direction'] ?? 'desc';

        $recordsQuery = AdminPeriodIndicatorValue::query()
            ->where('dataset_version_id', $version->id)
            ->with(['indicator', 'geography'])
            ->when(filled($validated['indicator'] ?? null), fn ($query) => $query->whereHas('indicator', fn ($indicators) => $indicators->where('code', $validated['indicator'])))
            ->when(filled($validated['geography'] ?? null), fn ($query) => $query->where('geography_code', 'like', '%'.$validated['geography'].'%'))
            ->when(filled($validated['date_from'] ?? null), fn ($query) => $query->whereDate('period_date', '>=', $validated['date_from']))
            ->when(filled($validated['date_to'] ?? null), fn ($query) => $query->whereDate('period_date', '<=', $validated['date_to']));

        match ($sort) {
            'geography' => $recordsQuery->orderBy('geography_code', $direction),
            'indicator' => $recordsQuery->orderBy(
                Indicator::query()
                    ->select('code')
                    ->whereColumn('indicators.id', 'admin_period_indicator_values.indicator_id'),
                $direction,
            ),
            'value' => $recordsQuery->orderBy('value', $direction),
            default => $recordsQuery->orderBy('period_date', $direction),
        };

        $records = $recordsQuery
            ->orderBy('id', $direction)
            ->paginate($perPage)
            ->onEachSide(1)
            ->withQueryString();

        $indicatorIds = AdminPeriodIndicatorValue::query()
            ->where('dataset_version_id', $version->id)
            ->select('indicator_id');

        return view('cp.rainfall.index', [
            'version' => $version,
            'records' => $records,
            'indicators' => Indicator::query()->whereIn('id', $indicatorIds)->orderBy('code')->get(['id', 'code', 'name']),
            'imports' => $this->rainfallImports($version),
            'filters' => $validated,
            'sort' => $sort,
            'direction' => $direction,
            'sourceName' => $version->dataset?->source?->name ?? 'Not recorded',
            'stats' => [
                'total_records' => AdminPeriodIndicatorValue::query()->where('dataset_version_id', $version->id)->count(),
                'latest_period' => AdminPeriodIndicatorValue::query()->where('dataset_version_id', $version->id)->max('period_date'),
            ],
        ]);
    }

    public function inject(Request $request, CsvDatasetImporter $importer): RedirectResponse
    {
        $validated = $request->validate([
            'rainfall_csv' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
            'period_type' => ['nullable', 'string', 'max:32'],
        ]);

        $version = $this->rainfallDatasetVersion();
        $directory = storage_path('app/climatehub/imports');
        File::ensureDirectoryExists($directory);
        $filename = 'rainfall-'.now()->format('YmdHis').'-'.Str::random(8).'.csv';
        $path = $validated['rainfall_csv']->move($directory, $filename)->getPathname();

        $importer->queue('long_admin_period_indicator', $path, $version, array_filter([
            'country_code' => 'NGA',
            'period_type' => $validated['period_type'] ?? null,
        ]));

        return redirect()->route('statamic.cp.climatehub.rainfall.index')
            ->with('status', 'Rainfall CSV is queued for staged validation.');
    }

    public function approveImport(Request $request, DatasetImport $import, CsvDatasetImporter $importer): RedirectResponse
    {
        abort_unless($import->dataset_version_id === $this->rainfallDatasetVersion()->id, 404);

        $userId = $request->user()?->getAuthIdentifier();

        if (! is_numeric($userId)) {
            abort(403);
        }

        try {
            $importer->approve($import, (int) $userId);
        } catch (CsvImportException $exception) {
            throw ValidationException::withMessages(['rainfall_import' => $exception->getMessage()]);
        }

        ApplyApprovedCsvDatasetImport::dispatch($import->id, (int) $userId)->onQueue('imports');

        return redirect()->route('statamic.cp.climatehub.rainfall.index')
            ->with('status', 'Rainfall import approved and queued for incremental application.');
    }

    public function edit(AdminPeriodIndicatorValue $rainfallValue): View
    {
        $this->assertRainfallRecord($rainfallValue);
        $rainfallValue->load(['indicator', 'geography', 'source', 'datasetVersion']);

        return view('cp.rainfall.edit', [
            'rainfallValue' => $rainfallValue,
            'indicators' => $this->rainfallIndicators($rainfallValue->dataset_version_id),
        ]);
    }

    public function update(Request $request, AdminPeriodIndicatorValue $rainfallValue): RedirectResponse
    {
        $this->assertRainfallRecord($rainfallValue);
        $validated = $request->validate([
            'value' => ['nullable', 'numeric'],
            'period_date' => ['required', 'date'],
            'period_type' => ['required', 'string', 'max:32'],
            'geography_code' => ['required', 'string', 'max:64'],
            'indicator_id' => ['required', 'integer', 'exists:indicators,id'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        if (! $this->rainfallIndicators($rainfallValue->dataset_version_id)->pluck('id')->contains((int) $validated['indicator_id'])) {
            throw ValidationException::withMessages(['indicator_id' => 'Choose an indicator used by the rainfall dataset.']);
        }

        $geography = Geography::query()->where('code', $validated['geography_code'])->first();

        if (! $geography) {
            throw ValidationException::withMessages(['geography_code' => 'Choose an existing canonical geography code.']);
        }

        $periodDate = Carbon::parse($validated['period_date'])->toDateString();
        $duplicate = AdminPeriodIndicatorValue::query()
            ->where('dataset_version_id', $rainfallValue->dataset_version_id)
            ->where('indicator_id', $validated['indicator_id'])
            ->where('geography_code', $geography->code)
            ->whereDate('period_date', $periodDate)
            ->where('period_type', $validated['period_type'])
            ->whereKeyNot($rainfallValue->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['period_date' => 'This change would duplicate an existing rainfall fact.']);
        }

        $previous = $rainfallValue->only(['value', 'period_date', 'period_type', 'geography_code', 'geography_id', 'indicator_id', 'record_key']);
        $updated = [
            'value' => $validated['value'],
            'period_date' => $periodDate,
            'period_type' => $validated['period_type'],
            'geography_code' => $geography->code,
            'geography_id' => $geography->id,
            'indicator_id' => (int) $validated['indicator_id'],
        ];
        $updated['record_key'] = hash('sha256', implode("\x1F", [
            'admin_period_indicator_values',
            $rainfallValue->dataset_version_id,
            $updated['indicator_id'],
            $updated['geography_code'],
            $updated['period_date'],
            $updated['period_type'],
        ]));

        DB::transaction(function () use ($rainfallValue, $updated, $previous, $validated, $request): void {
            $rainfallValue->forceFill($updated)->save();

            AdminPeriodIndicatorValueEdit::create([
                'admin_period_indicator_value_id' => $rainfallValue->id,
                'editor_user_id' => $request->user()?->getAuthIdentifier(),
                'previous_values' => $previous,
                'updated_values' => $updated,
                'reason' => $validated['reason'],
            ]);
        });

        return redirect()->route('statamic.cp.climatehub.rainfall.index')
            ->with('status', 'Rainfall fact updated and logged.');
    }

    private function rainfallDatasetVersion(): DatasetVersion
    {
        $dataset = Dataset::query()->where('code', 'nigeria_rainfall_subnational')->firstOrFail();

        return $dataset->versions()
            ->with('dataset.source')
            ->whereIn('status', ['published', 'active'])
            ->orderByDesc('activated_at')
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function rainfallImports(DatasetVersion $version)
    {
        $imports = $version->imports()
            ->with(['errors' => fn ($query) => $query->where('error_code', 'duplicate_source_file')])
            ->latest('id')
            ->paginate(10, ['*'], 'imports_page')
            ->onEachSide(1)
            ->withQueryString();

        $sourceHashes = $imports->getCollection()
            ->pluck('source_hash')
            ->filter()
            ->unique()
            ->values();

        if ($sourceHashes->isEmpty()) {
            return $imports;
        }

        $firstImportIdsByHash = DatasetImport::query()
            ->where('dataset_version_id', $version->id)
            ->whereIn('source_hash', $sourceHashes)
            ->selectRaw('source_hash, MIN(id) as first_import_id')
            ->groupBy('source_hash')
            ->pluck('first_import_id', 'source_hash');

        $imports->getCollection()->each(function (DatasetImport $import) use ($firstImportIdsByHash): void {
            $firstImportId = $firstImportIdsByHash->get($import->source_hash);

            if ($firstImportId && (int) $firstImportId !== $import->id) {
                $import->setAttribute('duplicate_of_import_id', (int) $firstImportId);
            }
        });

        return $imports;
    }

    private function assertRainfallRecord(AdminPeriodIndicatorValue $rainfallValue): void
    {
        abort_unless($rainfallValue->dataset_version_id === $this->rainfallDatasetVersion()->id, 404);
    }

    private function rainfallIndicators(int $versionId)
    {
        return Indicator::query()
            ->whereIn('id', AdminPeriodIndicatorValue::query()
                ->where('dataset_version_id', $versionId)
                ->select('indicator_id'))
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }
}

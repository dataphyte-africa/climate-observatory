<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Imports\CsvDatasetImporter;
use App\Imports\Exceptions\CsvImportException;
use App\Jobs\ApplyApprovedCsvDatasetImport;
use App\Models\CountryYearSectorGasValue;
use App\Models\CountryYearSectorGasValueEdit;
use App\Models\Dataset;
use App\Models\DatasetImport;
use App\Models\DatasetVersion;
use App\Models\Gas;
use App\Models\Sector;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmissionsCpController extends Controller
{
    public function index(Request $request): View
    {
        $version = $this->emissionsVersion();
        $filters = $request->validate([
            'country' => ['nullable', 'string', 'max:8'],
            'sector' => ['nullable', 'string', 'max:100'],
            'gas' => ['nullable', 'string', 'max:100'],
            'year_from' => ['nullable', 'integer', 'between:1850,2100', 'before_or_equal:year_to'],
            'year_to' => ['nullable', 'integer', 'between:1850,2100', 'after_or_equal:year_from'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
            'sort' => ['nullable', 'in:country,year,sector,gas,value'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $sort = $filters['sort'] ?? 'year';
        $direction = $filters['direction'] ?? 'desc';
        $recordsQuery = CountryYearSectorGasValue::query()
            ->where('dataset_version_id', $version->id)
            ->with(['sector', 'gas', 'source'])
            ->when(filled($filters['country'] ?? null), fn ($query) => $query->where('country_code', 'like', '%'.Str::upper($filters['country']).'%'))
            ->when(filled($filters['sector'] ?? null), fn ($query) => $query->whereHas('sector', fn ($sectors) => $sectors->where('code', $filters['sector'])))
            ->when(filled($filters['gas'] ?? null), fn ($query) => $query->whereHas('gas', fn ($gases) => $gases->where('code', $filters['gas'])))
            ->when(filled($filters['year_from'] ?? null), fn ($query) => $query->where('year', '>=', $filters['year_from']))
            ->when(filled($filters['year_to'] ?? null), fn ($query) => $query->where('year', '<=', $filters['year_to']));

        match ($sort) {
            'country' => $recordsQuery->orderBy('country_code', $direction),
            'sector' => $recordsQuery->orderBy(Sector::query()->select('name')->whereColumn('sectors.id', 'country_year_sector_gas_values.sector_id'), $direction),
            'gas' => $recordsQuery->orderBy(Gas::query()->select('name')->whereColumn('gases.id', 'country_year_sector_gas_values.gas_id'), $direction),
            'value' => $recordsQuery->orderBy('value', $direction),
            default => $recordsQuery->orderBy('year', $direction),
        };

        $records = $recordsQuery
            ->orderBy('id', $direction)
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->onEachSide(1)
            ->withQueryString();

        return view('cp.emissions.index', [
            'version' => $version,
            'records' => $records,
            'filters' => $filters,
            'imports' => $this->emissionsImports($version),
            'sort' => $sort,
            'direction' => $direction,
            'sectors' => Sector::query()->whereIn('id', CountryYearSectorGasValue::query()->where('dataset_version_id', $version->id)->select('sector_id'))->orderBy('name')->get(),
            'gases' => Gas::query()->whereIn('id', CountryYearSectorGasValue::query()->where('dataset_version_id', $version->id)->select('gas_id'))->orderBy('name')->get(),
            'stats' => [
                'total_records' => CountryYearSectorGasValue::query()->where('dataset_version_id', $version->id)->count(),
                'latest_year' => CountryYearSectorGasValue::query()->where('dataset_version_id', $version->id)->max('year'),
                'sources' => CountryYearSectorGasValue::query()->where('dataset_version_id', $version->id)->with('source')->get()->pluck('source.name')->filter()->unique()->join(', '),
            ],
        ]);
    }

    public function inject(Request $request, CsvDatasetImporter $importer): RedirectResponse
    {
        $validated = $request->validate(['emissions_csv' => ['required', 'file', 'mimes:csv,txt', 'max:51200']]);
        $upload = $validated['emissions_csv'];
        $filename = basename($upload->getClientOriginalName());

        if ($filename === '' || $filename === '.' || $filename === '..') {
            throw ValidationException::withMessages(['emissions_csv' => 'The CSV filename is invalid.']);
        }

        $directory = storage_path('app/climatehub/imports/emissions');
        File::ensureDirectoryExists($directory);
        $version = $this->emissionsVersion();
        $sourceHash = hash_file('sha256', $upload->getRealPath());
        $duplicate = DatasetImport::query()
            ->where('dataset_version_id', $version->id)
            ->where('source_hash', $sourceHash)
            ->oldest('id')
            ->first();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'emissions_csv' => "This CSV is the same file as import #{$duplicate->id} and was not staged again.",
            ]);
        }

        if (File::exists($directory.DIRECTORY_SEPARATOR.$filename)) {
            throw ValidationException::withMessages(['emissions_csv' => "An emissions file named [{$filename}] already exists."]);
        }

        $path = $upload->move($directory, $filename)->getPathname();

        try {
            $importer->queue('wide_country_sector_gas_year', $path, $version, [
                'original_filename' => $filename,
            ]);
        } catch (CsvImportException $exception) {
            File::delete($path);

            throw ValidationException::withMessages(['emissions_csv' => $exception->getMessage()]);
        }

        return redirect()->route('statamic.cp.climatehub.emissions.index')->with('status', 'Emissions CSV is queued for staged validation.');
    }

    public function approveImport(Request $request, DatasetImport $import, CsvDatasetImporter $importer): RedirectResponse
    {
        abort_unless($import->dataset_version_id === $this->emissionsVersion()->id, 404);
        $userId = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($userId), 403);

        try {
            $import = $importer->approve($import, (int) $userId);
        } catch (CsvImportException $exception) {
            throw ValidationException::withMessages(['emissions_import' => $exception->getMessage()]);
        }

        if ($import->status !== 'approved') {
            throw ValidationException::withMessages(['emissions_import' => 'Existing emissions facts were found. The import was rejected and cannot be approved.']);
        }

        ApplyApprovedCsvDatasetImport::dispatch($import->id, (int) $userId)->onQueue('imports');

        return redirect()->route('statamic.cp.climatehub.emissions.index')->with('status', 'Emissions import approved and queued for incremental application.');
    }

    public function edit(CountryYearSectorGasValue $emissionsValue): View
    {
        $this->assertEmissionsRecord($emissionsValue);
        $emissionsValue->load(['source', 'sector', 'gas']);

        return view('cp.emissions.edit', [
            'emissionsValue' => $emissionsValue,
            'sectors' => Sector::query()->orderBy('name')->get(),
            'gases' => Gas::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, CountryYearSectorGasValue $emissionsValue): RedirectResponse
    {
        $this->assertEmissionsRecord($emissionsValue);
        $validated = $request->validate([
            'country_code' => ['required', 'string', 'max:8'],
            'year' => ['required', 'integer', 'between:1850,2100'],
            'sector_id' => ['required', 'integer', 'exists:sectors,id'],
            'gas_id' => ['required', 'integer', 'exists:gases,id'],
            'value' => ['required', 'numeric'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $updated = [
            'country_code' => Str::upper(trim($validated['country_code'])),
            'year' => $validated['year'],
            'sector_id' => $validated['sector_id'],
            'gas_id' => $validated['gas_id'],
            'value' => $validated['value'],
        ];
        $updated['record_key'] = hash('sha256', implode("\x1F", ['country_year_sector_gas_values', $emissionsValue->dataset_version_id, $emissionsValue->source_id ?? 0, $updated['sector_id'], $updated['gas_id'], $updated['country_code'], $updated['year']]));

        $duplicate = CountryYearSectorGasValue::query()->where('record_key', $updated['record_key'])->whereKeyNot($emissionsValue->id)->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['year' => 'This change would duplicate an existing emissions fact.']);
        }

        DB::transaction(function () use ($emissionsValue, $updated, $validated, $request): void {
            $previous = $emissionsValue->only(['country_code', 'year', 'sector_id', 'gas_id', 'value', 'record_key']);
            $emissionsValue->forceFill($updated)->save();
            CountryYearSectorGasValueEdit::create([
                'country_year_sector_gas_value_id' => $emissionsValue->id,
                'editor_user_id' => $request->user()?->getAuthIdentifier(),
                'previous_values' => $previous,
                'updated_values' => $updated,
                'reason' => $validated['reason'],
            ]);
        });

        return redirect()->route('statamic.cp.climatehub.emissions.index')->with('status', 'Emissions fact updated and logged.');
    }

    private function emissionsVersion(): DatasetVersion
    {
        $dataset = Dataset::query()->where('code', 'climatewatch_historical_emissions')->firstOrFail();

        return $dataset->versions()->with('dataset.source')->whereIn('status', ['published', 'active'])->orderByDesc('activated_at')->orderByDesc('id')->firstOrFail();
    }

    private function assertEmissionsRecord(CountryYearSectorGasValue $emissionsValue): void
    {
        abort_unless($emissionsValue->dataset_version_id === $this->emissionsVersion()->id, 404);
    }

    private function emissionsImports(DatasetVersion $version)
    {
        $imports = $version->imports()
            ->with(['errors' => fn ($query) => $query->whereIn('error_code', ['duplicate_source_file', 'existing_dataset_records'])])
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
}

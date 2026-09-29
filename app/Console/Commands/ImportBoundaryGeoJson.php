<?php

namespace App\Console\Commands;

use App\Services\Geographies\BoundaryGeoJsonImporter;
use Illuminate\Console\Command;

class ImportBoundaryGeoJson extends Command
{
    protected $signature = 'climate:import-boundary-geojson
        {file : GeoJSON boundary fixture path}
        {level : Administrative level, currently 1 or 2}
        {--boundary-version= : Override boundary version}
        {--simplification=overview : Simplification class}
        {--source-name=boundary-fixture : Boundary source label}
        {--source-layer= : Boundary source layer}
        {--geometry-status=draft : Geometry publication status}
        {--preview : Scan and transform without persisting}';

    protected $description = 'Import admin-1/admin-2 boundary GeoJSON into database-backed geography tables.';

    public function handle(BoundaryGeoJsonImporter $importer): int
    {
        $options = [
            'boundary_version' => $this->option('boundary-version') ?: null,
            'simplification' => $this->option('simplification'),
            'source_name' => $this->option('source-name'),
            'source_layer' => $this->option('source-layer') ?: null,
            'geometry_status' => $this->option('geometry-status'),
        ];

        $summary = $this->option('preview')
            ? $importer->preview((string) $this->argument('file'), (int) $this->argument('level'), $options)
            : $importer->import((string) $this->argument('file'), (int) $this->argument('level'), $options);

        $this->info(($this->option('preview') ? 'Previewed' : 'Imported')." boundary GeoJSON level [{$summary['level']}] version [{$summary['boundary_version']}].");
        $this->line("Scanned features: {$summary['scanned_features']}");
        $this->line("Imported geographies: {$summary['imported_geographies']}");
        $this->line("Imported shapes: {$summary['imported_shapes']}");
        $this->line('Errors: '.count($summary['errors']));

        foreach (array_slice($summary['errors'], 0, 10) as $error) {
            $this->warn("Feature {$error['feature_index']}: {$error['message']}");
        }

        return $summary['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}

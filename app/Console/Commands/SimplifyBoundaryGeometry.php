<?php

namespace App\Console\Commands;

use App\Services\Geographies\BoundaryGeometrySimplifier;
use Illuminate\Console\Command;

class SimplifyBoundaryGeometry extends Command
{
    protected $signature = 'climate:simplify-boundary-geometry
        {--boundary-version=v01 : Boundary version to simplify}
        {--source-simplification=overview : Source simplification label}
        {--target-simplification=public-1mb : Target simplification label}
        {--source-status=published : Source geometry status}
        {--target-status=draft : Target geometry status}
        {--levels=1,2 : Comma-separated admin levels}
        {--tolerance=0.0002 : Douglas-Peucker tolerance in decimal degrees}
        {--precision=6 : Coordinate decimal places}
        {--preview : Generate and measure inside a rolled-back transaction}';

    protected $description = 'Derive smaller database-backed admin boundary geometry from an existing simplification label.';

    public function handle(BoundaryGeometrySimplifier $simplifier): int
    {
        $options = [
            'boundary_version' => $this->option('boundary-version'),
            'source_simplification' => $this->option('source-simplification'),
            'target_simplification' => $this->option('target-simplification'),
            'source_status' => $this->option('source-status'),
            'target_status' => $this->option('target-status'),
            'levels' => $this->option('levels'),
            'tolerance' => $this->option('tolerance'),
            'precision' => $this->option('precision'),
        ];

        $summary = $this->option('preview')
            ? $simplifier->preview($options)
            : $simplifier->simplify($options);

        $this->info(($this->option('preview') ? 'Previewed' : 'Generated')." boundary simplification [{$summary['target_simplification']}] from [{$summary['source_simplification']}].");
        $this->line("Boundary version: {$summary['boundary_version']}");
        $this->line("Source status: {$summary['source_status']}");
        $this->line("Target status: {$summary['target_status']}");
        $this->line("Tolerance: {$summary['tolerance']}");
        $this->line("Precision: {$summary['precision']}");

        foreach ($summary['levels'] as $level => $levelSummary) {
            $this->line("Level {$level}: {$levelSummary['source_count']} source shapes, {$levelSummary['target_count']} target shapes");
            $this->line("Level {$level} geometry bytes: {$levelSummary['source_geometry_bytes']} -> {$levelSummary['target_geometry_bytes']}");
            $this->line("Level {$level} points: {$levelSummary['source_points']} -> {$levelSummary['target_points']}");
        }

        return self::SUCCESS;
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geographies', function (Blueprint $table) {
            if (! Schema::hasColumn('geographies', 'source_pcode')) {
                $table->string('source_pcode')->nullable()->after('code')->index();
            }

            if (! Schema::hasColumn('geographies', 'boundary_version')) {
                $table->string('boundary_version')->nullable()->after('type')->index();
            }

            if (! Schema::hasColumn('geographies', 'valid_from')) {
                $table->date('valid_from')->nullable()->after('boundary_version');
            }

            if (! Schema::hasColumn('geographies', 'valid_to')) {
                $table->date('valid_to')->nullable()->after('valid_from');
            }

            if (! Schema::hasColumn('geographies', 'alternate_codes')) {
                $table->json('alternate_codes')->nullable()->after('valid_to');
            }
        });

        if (! Schema::hasTable('geography_aliases')) {
            Schema::create('geography_aliases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('geography_id')->constrained('geographies')->cascadeOnDelete();
                $table->string('source_name');
                $table->string('alias_type', 64)->default('name');
                $table->string('alias_value');
                $table->string('normalized_alias')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['source_name', 'alias_type', 'normalized_alias'], 'geo_alias_source_type_alias_unique');
            });
        }

        if (! Schema::hasTable('geography_shapes')) {
            Schema::create('geography_shapes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('geography_id')->constrained('geographies')->cascadeOnDelete();
                $table->string('boundary_version');
                $table->unsignedSmallInteger('geography_level');
                $table->string('geometry_status', 64)->default('draft');
                $table->string('simplification', 64)->default('overview');
                $table->string('source_path')->nullable();
                $table->string('source_layer')->nullable();
                $table->string('source_feature_id')->nullable();
                $table->longText('geometry_geojson')->nullable();
                $table->decimal('centroid_latitude', 10, 7)->nullable();
                $table->decimal('centroid_longitude', 10, 7)->nullable();
                $table->json('bbox')->nullable();
                $table->decimal('area_sq_km', 14, 4)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['geography_id', 'boundary_version', 'simplification'], 'geo_shapes_geo_version_simplification_unique');
                $table->index(['boundary_version', 'geography_level', 'geometry_status'], 'geo_shapes_version_level_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('geography_shapes');
        Schema::dropIfExists('geography_aliases');

        Schema::table('geographies', function (Blueprint $table) {
            foreach (['source_pcode', 'boundary_version', 'valid_from', 'valid_to', 'alternate_codes'] as $column) {
                if (Schema::hasColumn('geographies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

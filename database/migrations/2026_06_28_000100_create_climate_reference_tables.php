<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('symbol')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('organization_name')->nullable();
            $table->string('website_url')->nullable();
            $table->string('license_name')->nullable();
            $table->string('license_url')->nullable();
            $table->text('citation_template')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('sectors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('gases', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('geographies', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('source_pcode')->nullable()->index();
            $table->string('name');
            $table->string('country_code', 8)->nullable();
            $table->unsignedSmallInteger('level')->default(0);
            $table->foreignId('parent_id')->nullable()->constrained('geographies')->nullOnDelete();
            $table->string('type')->nullable();
            $table->string('boundary_version')->nullable()->index();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->json('alternate_codes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['country_code', 'level']);
        });

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

        Schema::create('indicators', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('value_type')->default('number');
            $table->string('grain')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicators');
        Schema::dropIfExists('geography_shapes');
        Schema::dropIfExists('geography_aliases');
        Schema::dropIfExists('geographies');
        Schema::dropIfExists('gases');
        Schema::dropIfExists('sectors');
        Schema::dropIfExists('sources');
        Schema::dropIfExists('units');
    }
};

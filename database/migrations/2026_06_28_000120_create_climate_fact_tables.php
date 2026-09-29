<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_year_sector_gas_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_version_id')->constrained('dataset_versions')->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->foreignId('sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->foreignId('gas_id')->nullable()->constrained('gases')->nullOnDelete();
            $table->string('country_code', 8);
            $table->unsignedSmallInteger('year');
            $table->decimal('value', 20, 6);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['dataset_version_id', 'country_code', 'year'], 'cysg_dataset_country_year_idx');
            $table->index(['sector_id', 'gas_id', 'year'], 'cysg_sector_gas_year_idx');
        });

        Schema::create('admin_period_indicator_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_version_id')->constrained('dataset_versions')->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->foreignId('geography_id')->nullable()->constrained('geographies')->nullOnDelete();
            $table->foreignId('indicator_id')->constrained('indicators')->cascadeOnDelete();
            $table->string('geography_code', 64);
            $table->date('period_date');
            $table->string('period_type', 32)->default('date');
            $table->decimal('value', 20, 6)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['dataset_version_id', 'geography_code', 'period_date'], 'apiv_dataset_geo_period_idx');
            $table->index(['indicator_id', 'period_date'], 'apiv_indicator_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_period_indicator_values');
        Schema::dropIfExists('country_year_sector_gas_values');
    }
};

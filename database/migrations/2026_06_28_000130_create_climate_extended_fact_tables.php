<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('country_year_indicator_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_version_id')->constrained('dataset_versions')->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->foreignId('indicator_id')->constrained('indicators')->cascadeOnDelete();
            $table->string('country_code', 8);
            $table->unsignedSmallInteger('year');
            $table->decimal('value', 20, 6);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['dataset_version_id', 'country_code', 'year'], 'cyiv_dataset_country_year_idx');
            $table->index(['indicator_id', 'year'], 'cyiv_indicator_year_idx');
        });

        Schema::create('country_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_version_id')->constrained('dataset_versions')->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->foreignId('document_type_id')->nullable()->constrained('document_types')->nullOnDelete();
            $table->string('country_code', 8);
            $table->string('document_code', 128);
            $table->string('title');
            $table->date('submission_date')->nullable();
            $table->string('status', 64)->default('published');
            $table->text('summary')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['dataset_version_id', 'country_code', 'document_code'], 'cdocs_dataset_country_doc_idx');
            $table->index(['document_type_id', 'status'], 'cdocs_type_status_idx');
        });

        Schema::create('country_document_sector_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_version_id')->constrained('dataset_versions')->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->foreignId('document_type_id')->nullable()->constrained('document_types')->nullOnDelete();
            $table->foreignId('sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->string('country_code', 8);
            $table->string('document_code', 128);
            $table->string('question_code', 64);
            $table->string('subsector_code', 64)->nullable();
            $table->longText('response_text')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['dataset_version_id', 'country_code', 'document_code'], 'cdsr_dataset_country_doc_idx');
            $table->index(['sector_id', 'question_code'], 'cdsr_sector_question_idx');
        });

        Schema::create('event_impacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_version_id')->constrained('dataset_versions')->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->foreignId('geography_id')->nullable()->constrained('geographies')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('country_code', 8);
            $table->string('geography_code', 64)->nullable();
            $table->date('event_date');
            $table->string('event_type', 64);
            $table->string('event_name')->nullable();
            $table->string('metric_code', 64);
            $table->decimal('value', 20, 6)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['dataset_version_id', 'geography_code', 'event_date'], 'eimp_dataset_geo_date_idx');
            $table->index(['event_type', 'metric_code'], 'eimp_type_metric_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_impacts');
        Schema::dropIfExists('country_document_sector_responses');
        Schema::dropIfExists('country_documents');
        Schema::dropIfExists('country_year_indicator_values');
        Schema::dropIfExists('document_types');
    }
};

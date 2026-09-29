<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datasets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('dataset_type')->nullable();
            $table->string('import_schema')->nullable();
            $table->string('default_fact_table')->nullable();
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->boolean('api_enabled')->default(true);
            $table->boolean('download_enabled')->default(true);
            $table->boolean('featured')->default(false);
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('dataset_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('datasets')->cascadeOnDelete();
            $table->string('version_label');
            $table->string('status')->default('draft');
            $table->string('source_filename')->nullable();
            $table->string('source_hash')->nullable();
            $table->unsignedBigInteger('row_count')->default(0);
            $table->timestamp('import_started_at')->nullable();
            $table->timestamp('import_completed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['dataset_id', 'version_label']);
        });

        Schema::create('dataset_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_version_id')->constrained('dataset_versions')->cascadeOnDelete();
            $table->string('disk')->nullable();
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();
        });

        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_version_id')->constrained('dataset_versions')->cascadeOnDelete();
            $table->string('schema_key');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('scanned_rows')->default(0);
            $table->unsignedBigInteger('accepted_rows')->default(0);
            $table->unsignedBigInteger('rejected_rows')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->unsignedBigInteger('row_number')->nullable();
            $table->string('column_name')->nullable();
            $table->string('error_code')->nullable();
            $table->text('message');
            $table->json('raw_row')->nullable();
            $table->timestamps();
            $table->index(['import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_errors');
        Schema::dropIfExists('imports');
        Schema::dropIfExists('dataset_files');
        Schema::dropIfExists('dataset_versions');
        Schema::dropIfExists('datasets');
    }
};

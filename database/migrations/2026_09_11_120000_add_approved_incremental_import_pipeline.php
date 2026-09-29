<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table): void {
            $table->string('source_hash', 64)->nullable()->after('schema_key');
            $table->timestamp('approved_at')->nullable()->after('completed_at');
            $table->foreignId('approved_by_user_id')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable()->after('approved_by_user_id');
            $table->foreignId('applied_by_user_id')->nullable()->after('applied_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('import_staged_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->unsignedBigInteger('row_number');
            $table->string('record_key', 64);
            $table->json('record_payload');
            $table->timestamps();

            $table->unique(['import_id', 'record_key'], 'import_stage_import_key_unique');
            $table->index(['import_id', 'id'], 'import_stage_import_id_idx');
        });

        $this->addRecordKey('country_year_sector_gas_values', 'cysg_record_key_unique');
        $this->addRecordKey('admin_period_indicator_values', 'apiv_record_key_unique');

        $this->backfillCountryYearSectorGasKeys();
        $this->backfillAdminPeriodIndicatorKeys();

        Schema::table('country_year_sector_gas_values', function (Blueprint $table): void {
            $table->unique('record_key', 'cysg_record_key_unique');
        });

        Schema::table('admin_period_indicator_values', function (Blueprint $table): void {
            $table->unique('record_key', 'apiv_record_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('admin_period_indicator_values', function (Blueprint $table): void {
            $table->dropUnique('apiv_record_key_unique');
            $table->dropColumn('record_key');
        });

        Schema::table('country_year_sector_gas_values', function (Blueprint $table): void {
            $table->dropUnique('cysg_record_key_unique');
            $table->dropColumn('record_key');
        });

        Schema::dropIfExists('import_staged_records');

        Schema::table('imports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('applied_by_user_id');
            $table->dropColumn('applied_at');
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn('approved_at');
            $table->dropColumn('source_hash');
        });
    }

    private function addRecordKey(string $tableName, string $indexName): void
    {
        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('record_key', 64)->nullable()->after('dataset_version_id');
        });
    }

    private function backfillCountryYearSectorGasKeys(): void
    {
        DB::table('country_year_sector_gas_values')
            ->select(['id', 'dataset_version_id', 'source_id', 'sector_id', 'gas_id', 'country_code', 'year'])
            ->orderBy('id')
            ->chunkById(1000, function ($rows): void {
                foreach ($rows as $row) {
                    $this->updateKey('country_year_sector_gas_values', $row->id, [
                        'country_year_sector_gas_values',
                        $row->dataset_version_id,
                        $row->source_id ?? 0,
                        $row->sector_id ?? 0,
                        $row->gas_id ?? 0,
                        $row->country_code,
                        $row->year,
                    ]);
                }
            });
    }

    private function backfillAdminPeriodIndicatorKeys(): void
    {
        DB::table('admin_period_indicator_values')
            ->select(['id', 'dataset_version_id', 'indicator_id', 'geography_code', 'period_date', 'period_type'])
            ->orderBy('id')
            ->chunkById(1000, function ($rows): void {
                foreach ($rows as $row) {
                    $this->updateKey('admin_period_indicator_values', $row->id, [
                        'admin_period_indicator_values',
                        $row->dataset_version_id,
                        $row->indicator_id,
                        $row->geography_code,
                        $row->period_date,
                        $row->period_type,
                    ]);
                }
            });
    }

    /** @param array<int, mixed> $parts */
    private function updateKey(string $tableName, int $id, array $parts): void
    {
        DB::table($tableName)->where('id', $id)->update([
            'record_key' => hash('sha256', implode("\x1F", $parts)),
        ]);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'country_year_indicator_values' => 'cyiv_record_key_unique',
        'country_documents' => 'cdocs_record_key_unique',
        'country_document_sector_responses' => 'cdsr_record_key_unique',
        'event_impacts' => 'eimp_record_key_unique',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName => $indexName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('record_key', 64)->nullable()->after('dataset_version_id');
            });
            $this->backfill($tableName);
            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->unique('record_key', $indexName);
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables, true) as $tableName => $indexName) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->dropUnique($indexName);
                $table->dropColumn('record_key');
            });
        }
    }

    private function backfill(string $tableName): void
    {
        DB::table($tableName)->orderBy('id')->chunkById(1000, function ($rows) use ($tableName): void {
            foreach ($rows as $row) {
                DB::table($tableName)->where('id', $row->id)->update([
                    'record_key' => hash('sha256', implode("\x1F", $this->keyParts($tableName, $row))),
                ]);
            }
        });
    }

    private function keyParts(string $tableName, object $row): array
    {
        return match ($tableName) {
            'country_year_indicator_values' => [$tableName, $row->dataset_version_id, $row->source_id ?? 0, $row->indicator_id, $row->country_code, $row->year],
            'country_documents' => [$tableName, $row->dataset_version_id, $row->country_code, $row->document_code],
            'country_document_sector_responses' => [$tableName, $row->dataset_version_id, $row->country_code, $row->document_code, $row->question_code, $row->subsector_code ?? ''],
            'event_impacts' => [$tableName, $row->dataset_version_id, $row->country_code, $row->geography_code ?? '', $row->event_date, $row->event_type, $row->event_name ?? '', $row->metric_code],
        };
    }
};

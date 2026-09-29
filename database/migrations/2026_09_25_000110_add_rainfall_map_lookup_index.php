<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_period_indicator_values', function (Blueprint $table): void {
            $table->index(
                ['dataset_version_id', 'indicator_id', 'period_type', 'geography_code', 'period_date'],
                'apiv_map_latest_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('admin_period_indicator_values', function (Blueprint $table): void {
            $table->dropIndex('apiv_map_latest_lookup_idx');
        });
    }
};

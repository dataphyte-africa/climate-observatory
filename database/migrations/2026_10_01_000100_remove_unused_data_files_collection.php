<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('collections')) {
            DB::table('collections')->where('handle', 'data_files')->delete();
        }

        if (Schema::hasTable('blueprints')) {
            DB::table('blueprints')->where('namespace', 'collections.data_files')->delete();
        }
    }

    public function down(): void
    {
        // The retired collection configuration is intentionally not recreated.
    }
};

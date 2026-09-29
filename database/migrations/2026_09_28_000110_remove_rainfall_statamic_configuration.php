<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('collections')) {
            DB::table('collections')->where('handle', 'rainfall_facts')->delete();
        }

        if (Schema::hasTable('blueprints')) {
            DB::table('blueprints')->where('namespace', 'collections.rainfall_facts')->delete();
        }

        if (Schema::hasTable('fieldsets')) {
            DB::table('fieldsets')->where('handle', 'rainfall_fact')->delete();
        }
    }

    public function down(): void
    {
        // The retired collection configuration is intentionally not recreated.
    }
};

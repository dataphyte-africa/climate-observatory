<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Statamic\Eloquent\Database\BaseMigration as Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable($this->prefix('asset_containers'))) {
            return;
        }

        DB::table($this->prefix('asset_containers'))
            ->where('handle', 'assets')
            ->update(['disk' => 'assets']);
    }

    public function down(): void
    {
        if (! Schema::hasTable($this->prefix('asset_containers'))) {
            return;
        }

        DB::table($this->prefix('asset_containers'))
            ->where('handle', 'assets')
            ->where('disk', 'assets')
            ->update(['disk' => 'public']);
    }
};

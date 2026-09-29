<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rainfall_statamic_entry_links', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_period_indicator_value_id');
            $table->unsignedBigInteger('entry_id');
            $table->timestamps();
            $table->unique('admin_period_indicator_value_id', 'rsel_fact_unique');
            $table->unique('entry_id', 'rsel_entry_unique');
            $table->foreign('admin_period_indicator_value_id', 'rsel_fact_fk')
                ->references('id')->on('admin_period_indicator_values')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rainfall_statamic_entry_links');
    }
};

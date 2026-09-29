<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_year_sector_gas_value_edits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('country_year_sector_gas_value_id');
            $table->unsignedBigInteger('editor_user_id')->nullable();
            $table->json('previous_values');
            $table->json('updated_values');
            $table->string('reason', 1000);
            $table->timestamps();
            $table->index(['country_year_sector_gas_value_id', 'created_at'], 'cysg_edit_value_created_idx');
            $table->foreign('country_year_sector_gas_value_id', 'cysg_edit_value_fk')
                ->references('id')->on('country_year_sector_gas_values')->cascadeOnDelete();
            $table->foreign('editor_user_id', 'cysg_edit_editor_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_year_sector_gas_value_edits');
    }
};

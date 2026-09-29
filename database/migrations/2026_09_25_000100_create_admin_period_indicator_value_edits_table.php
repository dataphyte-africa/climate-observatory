<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_period_indicator_value_edits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_period_indicator_value_id');
            $table->unsignedBigInteger('editor_user_id')->nullable();
            $table->json('previous_values');
            $table->json('updated_values');
            $table->string('reason', 1000);
            $table->timestamps();

            $table->index(['admin_period_indicator_value_id', 'created_at'], 'apiv_edit_value_created_idx');
            $table->foreign('admin_period_indicator_value_id', 'apiv_edit_value_fk')
                ->references('id')->on('admin_period_indicator_values')->cascadeOnDelete();
            $table->foreign('editor_user_id', 'apiv_edit_editor_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_period_indicator_value_edits');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('gift_history')->create('gift_history_import_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('gift_history_case_id')->nullable();
            $table->string('source_system', 50)->default('zouyo');
            $table->unsignedBigInteger('data_id')->nullable();

            // case_start / family_import / relationship_import などを想定
            $table->string('import_type', 50);
            $table->string('status', 50)->default('success');

            $table->string('source_table')->nullable();
            $table->unsignedInteger('source_count')->nullable();
            $table->unsignedInteger('imported_count')->nullable();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->index('gift_history_case_id', 'gift_history_import_logs_case_idx');
            $table->index(['source_system', 'data_id'], 'gift_history_import_logs_source_data_idx');
            $table->index('import_type', 'gift_history_import_logs_type_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('gift_history')->dropIfExists('gift_history_import_logs');
    }
};
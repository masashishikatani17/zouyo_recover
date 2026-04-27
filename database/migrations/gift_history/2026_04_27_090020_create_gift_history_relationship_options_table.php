<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('gift_history')->create('gift_history_relationship_options', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('gift_history_case_id');

            // 既存贈与名人側 relationship_masters からの初期取り込み情報
            $table->string('source_system', 50)->default('zouyo');
            $table->unsignedBigInteger('source_relationship_master_id')->nullable();
            $table->unsignedInteger('source_relation_no')->nullable();

            // 贈与履歴管理側で使う続柄候補
            $table->unsignedInteger('relation_no');
            $table->string('name')->nullable();
            $table->boolean('is_editable')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->unique(
                ['gift_history_case_id', 'relation_no'],
                'gift_history_relationship_options_case_relation_unique'
        );
            $table->index(
                ['gift_history_case_id', 'sort_order'],
                'gift_history_relationship_options_case_sort_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::connection('gift_history')->dropIfExists('gift_history_relationship_options');
    }
};

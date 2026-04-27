<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('gift_history')->create('gift_history_cases', function (Blueprint $table) {
            $table->id();

            // 既存の贈与名人側データとの対応関係
            $table->string('source_system', 50)->default('zouyo');
            $table->unsignedBigInteger('data_id');
            $table->unsignedBigInteger('proposal_header_id')->nullable();

            // 既存DB側の会社・グループ情報の控え
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();

            // 画面0表示用のスナップショット
            $table->string('customer_name_snapshot')->nullable();
            $table->string('data_name_snapshot')->nullable();
            $table->string('title_snapshot')->nullable();
            $table->timestamp('source_updated_at')->nullable();

            // 画面0表示用。贈与明細入力画面を作成後に更新する。
            $table->unsignedInteger('entries_count')->default(0);

            // 監査用。既存usersへの外部キーは張らない。
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->unique(['source_system', 'data_id'], 'gift_history_cases_source_data_unique');
            $table->index('customer_name_snapshot', 'gift_history_cases_customer_name_idx');
            $table->index('updated_at', 'gift_history_cases_updated_at_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('gift_history')->dropIfExists('gift_history_cases');
    }
};

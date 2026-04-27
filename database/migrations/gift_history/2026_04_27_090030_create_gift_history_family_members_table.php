<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('gift_history')->create('gift_history_family_members', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('gift_history_case_id');

            // 既存贈与名人側 proposal_family_members からの初期取り込み情報
            $table->string('source_system', 50)->default('zouyo');
            $table->unsignedBigInteger('source_family_member_id')->nullable();
            $table->unsignedBigInteger('source_data_id')->nullable();

            // 画面1は15行固定
            $table->unsignedTinyInteger('row_no');

            // 親族情報
            $table->string('name')->nullable();
            $table->string('gender', 20)->nullable();
            $table->unsignedInteger('relationship_code')->nullable();
            $table->string('relationship_name_snapshot')->nullable();
            $table->string('adoption_note')->nullable();
            $table->unsignedTinyInteger('heir_category')->nullable();

            // 法定相続割合
            $table->unsignedInteger('civil_share_bunbo')->nullable();
            $table->unsignedInteger('civil_share_bunsi')->nullable();
        $table->unsignedInteger('share_numerator')->nullable();
            $table->unsignedInteger('share_denominator')->nullable();

            // 相続税・贈与税確認用フラグ
            $table->boolean('surcharge_twenty_percent')->default(false);
            $table->boolean('tokurei_zouyo')->default(false);

            // 生年月日・年齢
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->unsignedTinyInteger('birth_month')->nullable();
            $table->unsignedTinyInteger('birth_day')->nullable();
            $table->unsignedSmallInteger('age')->nullable();

            // 贈与名人側No1からの参考値。履歴管理側では参考表示として保持する。
            $table->bigInteger('property_thousand')->nullable();
            $table->bigInteger('cash_thousand')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->unique(
                ['gift_history_case_id', 'row_no'],
                'gift_history_family_members_case_row_unique'
            );
            $table->index('gift_history_case_id', 'gift_history_family_members_case_idx');
            $table->index('relationship_code', 'gift_history_family_members_relationship_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('gift_history')->dropIfExists('gift_history_family_members');
    }
};
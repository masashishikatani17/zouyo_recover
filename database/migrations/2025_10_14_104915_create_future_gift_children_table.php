<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * 受贈者（No2〜9）一覧（1:N）
         * まずは親なしでも通るよう FK は未付与、data_id は INT UNSIGNED で作成
         */
        Schema::create('future_gift_recipients', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedInteger('data_id'); // ★後で親型に合わせて変更＆FK付与
            $table->index('data_id');
            $table->unsignedTinyInteger('recipient_no'); // 2..9
            $table->string('recipient_name', 100)->nullable();
            $table->timestamps();
            $table->unique(['data_id', 'recipient_no']); // 同一 data_id 内で一意
        });

        /**
         * 将来贈与プラン明細（1:N）
         * data_id × recipient_no × row_no（1..20）
         */
        Schema::create('future_gift_plan_entries', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedInteger('data_id'); // ★後で親型に合わせて変更＆FK付与
            $table->index('data_id');
            $table->unsignedTinyInteger('recipient_no'); // 2..9
            $table->unsignedTinyInteger('row_no');       // 1..20（回数）

            // 表示列：贈与年／年齢
            $table->unsignedSmallInteger('gift_year')->nullable();
            $table->unsignedTinyInteger('age')->nullable();

            // === 暦年贈与 ===
            $table->unsignedBigInteger('calendar_amount_thousand')->nullable();
            $table->unsignedBigInteger('calendar_basic_deduction_thousand')->nullable();
            $table->unsignedBigInteger('calendar_after_basic_thousand')->nullable();
            $table->unsignedBigInteger('calendar_special_tax_thousand')->nullable();
            $table->unsignedBigInteger('calendar_add_cum_thousand')->nullable();

            // === 精算課税贈与 ===
            $table->unsignedBigInteger('settlement_amount_thousand')->nullable();
            $table->unsignedBigInteger('settlement_110k_basic_thousand')->nullable();
            $table->unsignedBigInteger('settlement_after_basic_thousand')->nullable();
            $table->unsignedBigInteger('settlement_after_25m_thousand')->nullable();
            $table->unsignedBigInteger('settlement_tax20_thousand')->nullable();
            $table->unsignedBigInteger('settlement_add_cum_thousand')->nullable();

            $table->timestamps();

            // 一意・検索
            $table->unique(['data_id', 'recipient_no', 'row_no']);
            $table->index(['gift_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('future_gift_plan_entries');
        Schema::dropIfExists('future_gift_recipients');
    }
};

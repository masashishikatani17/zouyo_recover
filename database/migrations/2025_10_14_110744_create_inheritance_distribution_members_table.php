+<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * 遺産分割 明細（1:N）
         * まずは親なしでも通るよう FK は未付与、data_id は INT UNSIGNED で作成
         */
        Schema::create('inheritance_distribution_members', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            // ★ 後で親型（INT/BIGINT）に追随して変更＆FK付与します
            $table->unsignedInteger('data_id');
            $table->index('data_id');

            // 受贈者/相続人の番号（UIの選択肢に合わせる。例：2..9）
            $table->unsignedTinyInteger('recipient_no');

            // 課税価格（千円） 法定計算値
            $table->unsignedBigInteger('taxable_auto_value_thousand')->nullable();
            // 課税価格（千円） 手入力値
            $table->unsignedBigInteger('taxable_manu_value_thousand')->nullable();
            // その他の税額控除額（千円）
            $table->unsignedBigInteger('other_tax_credit_thousand')->nullable();

            $table->timestamps();

            // 同一 data_id 内で recipient_no の重複を禁止
            $table->unique(['data_id', 'recipient_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inheritance_distribution_members');
    }
};

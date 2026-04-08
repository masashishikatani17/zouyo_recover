<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 親 'datas' が未整備でも通るよう、まずは FK なしで作成
        Schema::create('future_gift_headers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id(); // BIGINT UNSIGNED
            // ★ まずは INT UNSIGNED で作成（後続マイグレーションで親型に合わせて変更 & FK付与）
            $table->unsignedInteger('data_id');
            $table->index('data_id');

            // 画面上部：贈与年月日（基準日）と贈与者名のスナップショット
            $table->unsignedSmallInteger('base_year')->nullable();
            $table->unsignedTinyInteger('base_month')->nullable();
            $table->unsignedTinyInteger('base_day')->nullable();
            $table->string('donor_name', 100)->nullable();

            $table->timestamps();

            // 1:1 を保証
            $table->unique('data_id');
            $table->index(['base_year', 'base_month', 'base_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('future_gift_headers');
    }
};

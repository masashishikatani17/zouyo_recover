<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 親 'datas' が未整備でも通るよう、まずはFKなしで作成
        Schema::create('past_gift_inputs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id(); // BIGINT UNSIGNED
            // ★ まずは INT UNSIGNED で作成（FKは後付けで親型に合わせて変更）
            $table->unsignedInteger('data_id');
            $table->index('data_id');

            // ヘッダ項目（例：相続開始日・贈与者名）
            $table->unsignedSmallInteger('inherit_year')->nullable();
            $table->unsignedTinyInteger('inherit_month')->nullable();
            $table->unsignedTinyInteger('inherit_day')->nullable();
            $table->string('donor_name', 100)->nullable();

            $table->timestamps();

            // 1:1 を保証
            $table->unique('data_id');
            $table->index(['inherit_year', 'inherit_month', 'inherit_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('past_gift_inputs');
    }
};

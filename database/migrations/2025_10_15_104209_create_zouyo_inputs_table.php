<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('zouyo_inputs')) return;
        Schema::create('zouyo_inputs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            // 親 'datas' が未整備でも通るよう、まずは INT で作る（後で親型に追随してFKを付与可能）
            $table->unsignedInteger('data_id');
            $table->index('data_id');
            // 必要なら認可整合に使う列
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('group_id')->nullable();
            // 入力一式
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique('data_id'); // 1:1 を保証
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zouyo_inputs');
    }
};

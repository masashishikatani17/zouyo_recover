<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datas', function (Blueprint $table) {
            // INT UNSIGNED 主キー（子テーブル側は INT/検出追随で整合します）
            $table->increments('id');
            // 認可に使う最低限のカラム
            $table->unsignedInteger('company_id')->default(1);
            $table->unsignedInteger('group_id')->default(1);
            // 任意：既存コードで参照する可能性のある項目
            $table->unsignedSmallInteger('kihu_year')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datas');
    }
};

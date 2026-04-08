<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('zouyo_syori_settings')) return;
        Schema::create('zouyo_syori_settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            // 親 'datas' がまだ無い環境でも通すため、まずは INT で作成（後で親型に追随＆FK付与）
            $table->unsignedInteger('data_id');
            $table->index('data_id');

            // 認可の整合に使う可能性のあるカラム
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('group_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            // 設定本体（JSON）… MariaDB 10.5 でも json可（内部はlongtext）
            $table->json('payload')->nullable();

            $table->timestamps();
            $table->unique('data_id'); // 1:1 を保証
            $table->index(['company_id','group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zouyo_syori_settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 親 'datas' が未整備でも通るよう、まずは FK なしで作成
        Schema::create('inheritance_distribution_headers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id(); // BIGINT UNSIGNED
            // ★ とりあえず INT で作成（後続で親型に追随してFK付与）
            $table->unsignedInteger('data_id');
            $table->index('data_id');

            // 分割方法（isanbunkatu のヘッダ想定）
            // 例：0=法定相続, 1=遺言, 2=協議, 3=代償分割, 4=現物, 5=換価, 6=その他
            $table->unsignedTinyInteger('method_code')->nullable();
            $table->string('method_note', 200)->nullable();

            $table->timestamps();

            // 1:1 を保証
            $table->unique('data_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inheritance_distribution_headers');
    }
};

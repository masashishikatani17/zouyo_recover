<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('future_gift_plan_entries', function (Blueprint $table) {
            // 将来の重複発生を防止（既存に同一インデックスが無い前提）
            $table->unique(['data_id','recipient_no','row_no'], 'uniq_future_gift_plan_row');
        });
    }

    public function down(): void
    {
        Schema::table('future_gift_plan_entries', function (Blueprint $table) {
            $table->dropUnique('uniq_future_gift_plan_row');
        });
    }
};

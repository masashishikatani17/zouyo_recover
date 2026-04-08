+<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('past_gift_settlement_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('past_gift_settlement_entries', 'row_no')) {
                $table->unsignedInteger('row_no')->nullable()->after('recipient_no');
            }
        });

        // 既存 null を一括で 1 に埋めるなどの暫定策（必要なら手直し）
        DB::table('past_gift_settlement_entries')->whereNull('row_no')->update(['row_no' => 1]);

        Schema::table('past_gift_settlement_entries', function (Blueprint $table) {
            // 冪等保存のための複合ユニーク
            // 既に似たインデックスがある場合は適宜リネーム
            $table->unique(['data_id', 'recipient_no', 'row_no'], 'pgse_data_recipient_row_unique');
        });
    }

    public function down(): void
    {
        Schema::table('past_gift_settlement_entries', function (Blueprint $table) {
            if (Schema::hasColumn('past_gift_settlement_entries', 'row_no')) {
                $table->dropUnique('pgse_data_recipient_row_unique');
                $table->dropColumn('row_no');
            }
        });
    }
};

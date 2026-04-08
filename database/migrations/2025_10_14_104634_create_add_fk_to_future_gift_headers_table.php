<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 親 or 子が無ければ何もしない（後で再実行OK）
        if (!Schema::hasTable('datas')) return;

        // 親 datas.id の実型（int / bigint）を検出
        $col = DB::selectOne("
            SELECT COLUMN_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'datas'
              AND COLUMN_NAME = 'id'
            LIMIT 1
        ");
        if (!$col || empty($col->COLUMN_TYPE)) return;
        $useBig = str_contains(strtolower($col->COLUMN_TYPE), 'bigint');

        foreach (['future_gift_recipients','future_gift_plan_entries'] as $t) {
            if (!Schema::hasTable($t)) continue;

            // 既存FKがあれば外す（無ければ無視）
            Schema::table($t, function (Blueprint $table) use ($t) {
                try { $table->dropForeign("{$t}_data_id_foreign"); } catch (\Throwable $e) {}
            });

            // 親型に data_id を合わせる
            Schema::table($t, function (Blueprint $table) use ($useBig) {
                if ($useBig) {
                    $table->unsignedBigInteger('data_id')->change();
                } else {
                    $table->unsignedInteger('data_id')->change();
                }
            });

            // 外部キー付与
            Schema::table($t, function (Blueprint $table) {
                $table->foreign('data_id')
                      ->references('id')->on('datas')
                      ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        foreach (['future_gift_recipients','future_gift_plan_entries'] as $t) {
            if (!Schema::hasTable($t)) continue;
            Schema::table($t, function (Blueprint $table) use ($t) {
                try { $table->dropForeign("{$t}_data_id_foreign"); } catch (\Throwable $e) {}
            });
        }
    }
};

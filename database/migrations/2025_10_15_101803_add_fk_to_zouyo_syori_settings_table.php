<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('datas') || !Schema::hasTable('zouyo_syori_settings')) return;

        // 親 datas.id の実型を検出（int or bigint）
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

        // 既存のFKがあれば外す（あっても無くてもOK）
        try { DB::statement("ALTER TABLE `zouyo_syori_settings` DROP FOREIGN KEY `zouyo_syori_settings_data_id_foreign`"); } catch (\Throwable $e) {}

        // 親の型に data_id を合わせる（生SQLで実施）
        try {
            DB::statement("ALTER TABLE `zouyo_syori_settings` MODIFY `data_id` ".($useBig?'BIGINT':'INT')." UNSIGNED NOT NULL");
        } catch (\Throwable $e) {}

        // data_id にインデックスが無ければ付与
        $hasIndex = DB::selectOne("
            SELECT 1
              FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'zouyo_syori_settings'
               AND INDEX_NAME   = 'data_id'
        ");
        if (!$hasIndex) {
            try { DB::statement("ALTER TABLE `zouyo_syori_settings` ADD INDEX `data_id`(`data_id`)"); } catch (\Throwable $e) {}
        }

        // 外部キー付与
        try {
            DB::statement("ALTER TABLE `zouyo_syori_settings`
                           ADD CONSTRAINT `zouyo_syori_settings_data_id_foreign`
                           FOREIGN KEY (`data_id`) REFERENCES `datas`(`id`) ON DELETE CASCADE");
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        try { DB::statement("ALTER TABLE `zouyo_syori_settings` DROP FOREIGN KEY `zouyo_syori_settings_data_id_foreign`"); } catch (\Throwable $e) {}
    }
};

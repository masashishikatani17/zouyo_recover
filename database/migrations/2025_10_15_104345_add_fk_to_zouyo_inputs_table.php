<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('datas') || !Schema::hasTable('zouyo_inputs')) return;

        $col = DB::selectOne("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
                              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='datas' AND COLUMN_NAME='id'");
        if (!$col) return;
        $useBig = str_contains(strtolower($col->COLUMN_TYPE),'bigint');

        try { DB::statement("ALTER TABLE `zouyo_inputs` DROP FOREIGN KEY `zouyo_inputs_data_id_foreign`"); } catch (\Throwable $e) {}

        try {
            DB::statement("ALTER TABLE `zouyo_inputs` MODIFY `data_id` ".($useBig?'BIGINT':'INT')." UNSIGNED NOT NULL");
        } catch (\Throwable $e) {}

        $hasIndex = DB::selectOne("SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
                                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='zouyo_inputs' AND INDEX_NAME='data_id'");
        if (!$hasIndex) {
            try { DB::statement("ALTER TABLE `zouyo_inputs` ADD INDEX `data_id`(`data_id`)"); } catch (\Throwable $e) {}
        }

        try {
            DB::statement("ALTER TABLE `zouyo_inputs`
                           ADD CONSTRAINT `zouyo_inputs_data_id_foreign`
                           FOREIGN KEY (`data_id`) REFERENCES `datas`(`id`) ON DELETE CASCADE");
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        try { DB::statement("ALTER TABLE `zouyo_inputs` DROP FOREIGN KEY `zouyo_inputs_data_id_foreign`"); } catch (\Throwable $e) {}
    }
};

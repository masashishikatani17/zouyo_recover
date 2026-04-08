 <?php
 
 use Illuminate\Database\Migrations\Migration;
 use Illuminate\Database\Schema\Blueprint;
 use Illuminate\Support\Facades\Schema;
 use Illuminate\Support\Facades\DB;
 
 return new class extends Migration
 {
     public function up(): void
     {
         if (!Schema::hasTable('datas')) return;
 
         // 親の id 型検出（int or bigint）
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
 
         $tables = [
             'proposal_headers',
             'proposal_family_members',
             'past_gift_inputs',
             'past_gift_recipients',
             'past_gift_calendar_entries',
             'past_gift_settlement_entries',
             'future_gift_headers',
             'future_gift_recipients',
             'future_gift_plan_entries',
             'inheritance_distribution_headers',
             'inheritance_distribution_members',
         ];
 
        foreach ($tables as $t) {
            if (!Schema::hasTable($t) || !Schema::hasColumn($t, 'data_id')) continue;

            // 1) 既存の data_id 外部キー名を列挙して DROP（存在する分だけ）
            $fks = DB::select("
                SELECT CONSTRAINT_NAME
                  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = ?
                   AND COLUMN_NAME  = 'data_id'
                   AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$t]);
            foreach ($fks as $fk) {
                $name = $fk->CONSTRAINT_NAME;
                try { DB::statement("ALTER TABLE `{$t}` DROP FOREIGN KEY `{$name}`"); } catch (\Throwable $e) {}
            }

            // 2) 親型に合わせて data_id の型変更（生SQLで実施：doctrine不要）
            try {
                if ($useBig) {
                    DB::statement("ALTER TABLE `{$t}` MODIFY `data_id` BIGINT UNSIGNED NOT NULL");
                } else {
                    DB::statement("ALTER TABLE `{$t}` MODIFY `data_id` INT UNSIGNED NOT NULL");
                }
            } catch (\Throwable $e) {
                // 型変更が不要/不可なら続行（既に一致しているケース）
            }

            // 3) data_id にインデックスが無ければ付与（FKの前提）
            $hasIndex = DB::selectOne("
                SELECT 1
                  FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = ?
                   AND INDEX_NAME   = 'data_id'
            ", [$t]);
            if (!$hasIndex) {
                try { DB::statement("ALTER TABLE `{$t}` ADD INDEX `data_id` (`data_id`)"); } catch (\Throwable $e) {}
            }

            // 4) 外部キーを新規付与（重複回避）
            $exists = DB::selectOne("
                SELECT 1
                  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = ?
                   AND COLUMN_NAME  = 'data_id'
                   AND REFERENCED_TABLE_NAME = 'datas'
                 LIMIT 1
            ", [$t]);
            if (!$exists) {
                $fkName = "{$t}_data_id_foreign";
                try {
                    DB::statement("ALTER TABLE `{$t}` ADD CONSTRAINT `{$fkName}` FOREIGN KEY (`data_id`) REFERENCES `datas`(`id`) ON DELETE CASCADE");
                } catch (\Throwable $e) {
                    // 既に別名FKが付いた等のケースは無視
                }
            }
        }
     }
 
     public function down(): void
     {
         // 外部キーのみ外す（型は戻さない）
         $tables = [
             'proposal_headers',
             'proposal_family_members',
             'past_gift_inputs',
             'past_gift_recipients',
             'past_gift_calendar_entries',
             'past_gift_settlement_entries',
             'future_gift_headers',
             'future_gift_recipients',
             'future_gift_plan_entries',
             'inheritance_distribution_headers',
             'inheritance_distribution_members',
         ];
        foreach ($tables as $t) {
            if (!Schema::hasTable($t) || !Schema::hasColumn($t, 'data_id')) continue;
            $fks = DB::select("
                SELECT CONSTRAINT_NAME
                  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = ?
                   AND COLUMN_NAME  = 'data_id'
                   AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$t]);
            foreach ($fks as $fk) {
                $name = $fk->CONSTRAINT_NAME;
                try { DB::statement("ALTER TABLE `{$t}` DROP FOREIGN KEY `{$name}`"); } catch (\Throwable $e) {}
            }
        }
     }
 };

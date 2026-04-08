<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 親 'datas' が存在しないなら何もしない
        if (!Schema::hasTable('datas')) {
            return;
        }

        // 親の id 型を取得（int / bigint のいずれか）
        $col = DB::selectOne("
            SELECT COLUMN_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'datas'
              AND COLUMN_NAME = 'id'
            LIMIT 1
        ");
        if (!$col || empty($col->COLUMN_TYPE)) {
            return;
        }
        $columnType = strtolower($col->COLUMN_TYPE);
        $useBig = str_contains($columnType, 'bigint');

        // proposal_headers
        Schema::table('proposal_headers', function (Blueprint $table) {
            // 既存の外部キーがあれば一旦外す（無ければ無視される）
            try { $table->dropForeign('proposal_headers_data_id_foreign'); } catch (\Throwable $e) {}
        });
        // 型を親に合わせる
        Schema::table('proposal_headers', function (Blueprint $table) use ($useBig) {
            if ($useBig) {
                $table->unsignedBigInteger('data_id')->change();
            } else {
                $table->unsignedInteger('data_id')->change();
            }
        });
        // 外部キー付与
        Schema::table('proposal_headers', function (Blueprint $table) {
            $table->foreign('data_id')
                ->references('id')->on('datas')
                ->onDelete('cascade');
        });

        // proposal_family_members
        Schema::table('proposal_family_members', function (Blueprint $table) {
            try { $table->dropForeign('proposal_family_members_data_id_foreign'); } catch (\Throwable $e) {}
        });
        Schema::table('proposal_family_members', function (Blueprint $table) use ($useBig) {
            if ($useBig) {
                $table->unsignedBigInteger('data_id')->change();
            } else {
                $table->unsignedInteger('data_id')->change();
            }
        });
        Schema::table('proposal_family_members', function (Blueprint $table) {
            $table->foreign('data_id')
                ->references('id')->on('datas')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // 外部キーのみを外す（型変更はロールバックしない）
        if (Schema::hasTable('proposal_headers')) {
            Schema::table('proposal_headers', function (Blueprint $table) {
                try { $table->dropForeign('proposal_headers_data_id_foreign'); } catch (\Throwable $e) {}
            });
        }
        if (Schema::hasTable('proposal_family_members')) {
            Schema::table('proposal_family_members', function (Blueprint $table) {
                try { $table->dropForeign('proposal_family_members_data_id_foreign'); } catch (\Throwable $e) {}
            });
        }
    }
};

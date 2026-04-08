<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
    public function up(): void
    {
        // 重複 (data_id, recipient_no, row_no) のうち最新 id 以外を削除
        // ※ 事前にテーブル名を明示
        $table = 'future_gift_plan_entries';

        // 最新 id セット
        $latestIds = DB::table("$table as f")
            ->selectRaw('MAX(f.id) as id')
            ->groupBy('f.data_id','f.recipient_no','f.row_no')
            ->pluck('id')
            ->toArray();

        if (!empty($latestIds)) {
            // 最新以外（NOT IN 最新id）を削除
            DB::table($table)
                ->whereNotIn('id', $latestIds)
                ->delete();
        }
    }

    public function down(): void
    {
        // 破壊的削除のためロールバックなし
    }
};

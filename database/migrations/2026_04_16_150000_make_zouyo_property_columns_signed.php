<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // proposal_family_members.property_thousand が負数を保存できるようにする
        DB::statement("
            ALTER TABLE proposal_family_members
            MODIFY property_thousand BIGINT NULL
        ");

        // 合計行も将来的に負数を取り得るため signed に寄せる
        DB::statement("
            ALTER TABLE proposal_headers
            MODIFY property_total_thousand BIGINT NULL
        ");
    }

    public function down(): void
    {
        // 負数データが入った後に unsigned へ戻すと失敗するため no-op
        // 必要なら負数データを解消した上で個別に戻してください。
    }
};
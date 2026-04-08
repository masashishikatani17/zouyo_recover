+<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('proposal_family_members', function (Blueprint $table) {
            // 民法上の法定相続割合（表示用）
            // 例：'1/2', '1/3', '2/3' など。可変長表示に対応するため string として定義。
            $table->integer('civil_share_bunsi')
                  ->nullable()
                  ->comment('民法上の法定相続割合（分子）')
                  ->after('heir_category');
            $table->integer('civil_share_bunbo')
                  ->nullable()
                  ->comment('民法上の法定相続割合（分母）')
                  ->after('heir_category');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('proposal_family_members', function (Blueprint $table) {
            $table->dropColumn('civil_share_bunsi');
            $table->dropColumn('civil_share_bunbo');
        });
    }
};

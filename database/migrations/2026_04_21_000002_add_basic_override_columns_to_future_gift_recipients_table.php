<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('future_gift_recipients', function (Blueprint $table) {
            $table->boolean('calendar_basic_override_enabled')
                ->default(false)
                ->comment('暦年課税の基礎控除額修正を受贈者ごとに有効化するか');

            $table->boolean('settlement_basic_override_enabled')
                ->default(false)
                ->comment('精算課税の基礎控除額修正を受贈者ごとに有効化するか');
        });
    }

    public function down(): void
    {
        Schema::table('future_gift_recipients', function (Blueprint $table) {
            $table->dropColumn('calendar_basic_override_enabled');
            $table->dropColumn('settlement_basic_override_enabled');
        });
    }
};